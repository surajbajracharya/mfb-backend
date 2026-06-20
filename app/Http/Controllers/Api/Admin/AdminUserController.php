<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Controller;
use App\Models\ChallengeDay;
use App\Models\ChallengeDayProgress;
use App\Models\ChallengeEnrollment;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = app(\App\Services\TenantContext::class)->companyId();

        $query = User::withoutGlobalScope('company')
            ->with(["roles", "company", "latestVerification"])
            ->withCount(['planSubscriptions as active_plan_count' => fn ($q) => $q->where('status', 'active')])
            ->where(fn($q) => $companyId
                ? $q->where('users.company_id', $companyId)->orWhereNull('users.company_id')
                : $q
            );

        // type=customer / type=admin
        $type = $request->input('type');
        if ($type === 'customer') {
            $query->whereHas('roles', fn ($q) => $q->where('name', 'user'));
        } elseif ($type === 'admin') {
            $query->whereHas('roles', fn ($q) => $q->where('name', '!=', 'user'));
        }

        // Search: name, email, phone
        if ($s = $request->input('search')) {
            $query->where(fn ($q) => $q
                ->where('name',  'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")
            );
        }

        // Status: active (email verified) / inactive (unverified)
        if ($request->status === 'active') {
            $query->whereNotNull('email_verified_at');
        } elseif ($request->status === 'inactive') {
            $query->whereNull('email_verified_at');
        }

        // Source filter
        if (in_array($request->source, ['self_registered', 'admin_created', 'csv_import'])) {
            $query->where('source', $request->source);
        }

        // User type: premium = has active plan, general = no active plan
        if ($request->user_type === 'premium') {
            $query->whereHas('planSubscriptions', fn ($q) => $q->where('status', 'active'));
        } elseif ($request->user_type === 'general') {
            $query->whereDoesntHave('planSubscriptions', fn ($q) => $q->where('status', 'active'));
        }

        // Join date range
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sort
        match ($request->input('sort', 'name_asc')) {
            'name_desc'  => $query->orderBy('name', 'desc'),
            'email_asc'  => $query->orderBy('email', 'asc'),
            'email_desc' => $query->orderBy('email', 'desc'),
            'oldest'     => $query->orderBy('created_at', 'asc'),
            'newest'     => $query->orderBy('created_at', 'desc'),
            default      => $query->orderBy('name', 'asc'),
        };

        return response()->json($query->paginate(25));
    }
    public function show(string $id): JsonResponse
    {
        $user = User::withoutGlobalScope('company')->with([
            'roles',
            'company',
            'latestVerification',
            'enrollments.course:id,title,slug,thumbnail,type,level',
            'orders.items.purchasable',
            'eventTickets.event:id,title,slug,starts_at,venue_name,hero_image',
            'eventTickets.orderItem.order:id,order_number,payment_method',
            'appointments' => fn($q) => $q->with(['type:id,title,duration_minutes,images', 'orderItem.order:id,order_number']),
            'certificates.course:id,title,slug',
            'reviews.course:id,title,slug',
            'resourceViews.resource:id,title,slug,type,thumbnail',
            'planSubscriptions' => fn($q) => $q->with(['plan' => fn($pq) => $pq->select('id','name','price','interval','interval_count')->with('items')]),
        ])->findOrFail($id);

        // Resolve plan item titles (batch by type — max 4 extra queries)
        $allItems = $user->planSubscriptions->flatMap(fn ($s) => $s->plan?->items ?? collect());
        $byType   = $allItems->groupBy('type');
        $titleMap = [];
        foreach ($byType as $type => $items) {
            $ids = $items->pluck('item_id')->filter()->unique()->values()->all();
            $titleMap[$type] = match ($type) {
                'course'           => \App\Models\Course::whereIn('id', $ids)->pluck('title', 'id'),
                'event'            => \App\Models\Event::whereIn('id', $ids)->pluck('title', 'id'),
                'appointment_type' => \App\Models\AppointmentType::whereIn('id', $ids)->pluck('title', 'id'),
                'resource'         => \App\Models\Resource::whereIn('id', $ids)->pluck('title', 'id'),
                default            => collect(),
            };
        }
        foreach ($user->planSubscriptions as $sub) {
            foreach ($sub->plan?->items ?? [] as $item) {
                $item->setAttribute('item_title', $titleMap[$item->type][$item->item_id] ?? null);
            }
        }

        // Attach progress per enrollment
        $user->enrollments->each(function ($enrollment) use ($user) {
            if (!$enrollment->course) return;
            $totalLectures = \App\Models\CourseLecture::whereHas(
                'section', fn ($q) => $q->where('course_id', $enrollment->course->id)
            )->count();
            $completedLectures = \App\Models\CourseProgress::where('user_id', $user->id)
                ->where('completed', true)
                ->whereHas('lecture', fn ($q) => $q->whereHas(
                    'section', fn ($q2) => $q2->where('course_id', $enrollment->course->id)
                ))->count();
            $enrollment->setAttribute('total_lectures', $totalLectures);
            $enrollment->setAttribute('completed_lectures', $completedLectures);
            $enrollment->setAttribute('progress_percent',
                $totalLectures > 0 ? (int) round($completedLectures / $totalLectures * 100) : 0
            );
        });

        return response()->json(['data' => $user]);
    }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "name"     => ["required", "string"],
            "email"    => ["required", "email", "unique:users,email"],
            "password" => ["required", "string", "min:8"],
            "role"     => ["sometimes", "string", "exists:roles,name"],
        ]);

        $plainPassword = $data["password"];

        $user = User::create([
            "name"     => $data["name"],
            "email"    => $data["email"],
            "password" => bcrypt($plainPassword),
            "source"   => "admin_created",
        ]);
        // email_verified_at is not in $fillable, so set it directly via DB.
        \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update([
            'email_verified_at' => now(),
        ]);
        $user->refresh();
        $role = $request->user()->hasRole('super_admin') ? ($data["role"] ?? "user") : "user";
        $user->assignRole($role);

        $adminUrl = rtrim(env('ADMIN_URL', url('/')), '/');
        EmailService::send($user->email, 'admin_user_created', [
            '{username}'      => $user->name,
            '{email}'         => $user->email,
            '{temp_password}' => $plainPassword,
            '{login_url}'     => $adminUrl . '/admin/login',
            '{site_name}'     => Setting::getValue('site_name', config('app.name')),
        ]);

        return response()->json($user->load("roles"), 201);
    }
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::withoutGlobalScope('company')->with('roles')->findOrFail($id);
        $data = $request->validate([
            "name"     => ["sometimes", "string"],
            "email"    => ["sometimes", "email", "unique:users,email," . $id],
            "password" => ["sometimes", "nullable", "string", "min:8"],
            "phone"    => ["sometimes", "nullable", "string", "max:30"],
            "avatar"           => ["sometimes", "nullable", "string"],
            "bio"              => ["sometimes", "nullable", "string"],
            "interests"        => ["sometimes", "nullable", "array"],
            "interests.*"      => ["string", "max:100"],
            "experience_level" => ["sometimes", "nullable", "in:beginner,intermediate,experienced"],
            "role"     => ["sometimes", "string", "exists:roles,name"],
            "active"   => ["sometimes", "boolean"],
        ]);

        $frontendUrl      = rtrim(config('app.frontend_url', url('/')), '/');
        $wasActive        = !is_null($user->email_verified_at);
        $plainPassword    = !empty($data['password']) ? $data['password'] : null;

        if (isset($data["role"]) && $request->user()->hasAnyRole(['super_admin', 'admin'])) {
            $user->syncRoles([$data["role"]]);
        }

        $activating   = false;
        $deactivating = false;
        if (array_key_exists("active", $data) && $request->user()->hasAnyRole(['super_admin', 'admin'])) {
            $nowActive = (bool) $data["active"];
            $user->email_verified_at = $nowActive ? now() : null;
            $activating   = !$wasActive && $nowActive;
            $deactivating = $wasActive && !$nowActive;

            // When activating an account that has a submitted ID verification, mark the user as identity-verified
            if ($activating && !$user->is_identity_verified) {
                $hasSubmittedProof = \App\Models\IdentityVerification::where('user_id', $user->id)
                    ->where('status', 'submitted')
                    ->exists();
                if ($hasSubmittedProof) {
                    $user->is_identity_verified = true;
                }
            }
        }

        if ($plainPassword) {
            $data['password'] = bcrypt($plainPassword);
        } else {
            unset($data['password']);
        }
        unset($data["role"], $data["active"]);
        $user->update($data);

        // Password changed by admin — revoke all sessions so the user must re-login
        if ($plainPassword) {
            $user->tokens()->delete();
            EmailService::send($user->email, 'password_changed', [
                '{username}'  => $user->name,
                '{email}'     => $user->email,
                '{site_name}' => Setting::getValue('site_name', config('app.name')),
            ]);
        }

        // Account activated
        if ($activating) {
            EmailService::send($user->email, 'account_activated', [
                '{username}'      => $user->name,
                '{email}'         => $user->email,
                '{dashboard_url}' => $frontendUrl . '/dashboard',
                '{site_name}'     => Setting::getValue('site_name', config('app.name')),
            ]);
        }

        // Account deactivated — revoke all sessions immediately
        if ($deactivating) {
            $user->tokens()->delete();
            EmailService::send($user->email, 'account_deactivated', [
                '{username}'  => $user->name,
                '{email}'     => $user->email,
                '{site_name}' => Setting::getValue('site_name', config('app.name')),
            ]);
        }

        return response()->json($user->load(["roles", "company"]));
    }
    public function destroy(string $id): JsonResponse
    {
        $user = User::withoutGlobalScope('company')->findOrFail($id);
        if ($user->hasRole('super_admin')) {
            return response()->json(['message' => 'Super admin accounts cannot be deleted.'], 403);
        }
        $user->tokens()->delete();
        $user->delete(); // soft delete — moves to bin
        return response()->json(["message" => "User moved to bin."]);
    }

    public function bin(Request $request): JsonResponse
    {
        $companyId = app(\App\Services\TenantContext::class)->companyId();

        $query = User::withoutGlobalScope('company')->onlyTrashed()
            ->with(['roles', 'company'])
            ->withCount(['planSubscriptions as active_plan_count' => fn($q) => $q->where('status', 'active')])
            ->where(fn($q) => $companyId
                ? $q->where('users.company_id', $companyId)->orWhereNull('users.company_id')
                : $q
            );

        $type = $request->input('type');
        if ($type === 'admin') {
            $query->whereHas('roles', fn ($q) => $q->where('name', '!=', 'user'));
        } elseif ($type === 'customer') {
            $query->whereHas('roles', fn ($q) => $q->where('name', 'user'));
        }

        if ($s = $request->input('search')) {
            $query->where(fn($q) => $q
                ->where('name',  'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
            );
        }
        return response()->json($query->latest('deleted_at')->paginate(25));
    }

    public function restore(string $id): JsonResponse
    {
        $user = User::withoutGlobalScope('company')->onlyTrashed()->findOrFail($id);
        $user->restore();
        return response()->json(["message" => "User restored."]);
    }

    public function forceDestroy(Request $request, string $id): JsonResponse
    {
        $user = User::withoutGlobalScope('company')->onlyTrashed()->with(['resources'])->findOrFail($id);

        if ($user->hasRole('super_admin')) {
            return response()->json(['message' => 'Super admin accounts cannot be permanently deleted.'], 403);
        }

        // Reassign content to super admin before deleting
        if ($request->boolean('reassign_to_super_admin')) {
            $superAdmin = User::withoutGlobalScope('company')
                ->role('super_admin')
                ->first();

            if ($superAdmin) {
                \DB::table('courses')->where('user_id', $user->id)->update(['user_id' => $superAdmin->id]);
                \DB::table('resources')->where('user_id', $user->id)->update(['user_id' => $superAdmin->id]);
                \DB::table('challenges')->where('user_id', $user->id)->update(['user_id' => $superAdmin->id]);
            }

            // Skip deleting resource files since they now belong to super admin
            $user->forceDelete();
            return response()->json(["message" => "User permanently deleted. Content reassigned to Super Admin."]);
        }

        // Delete everything — clean up associated files
        $toDelete  = [];
        $urlToPath = fn($url) => preg_match('#/storage/(.+)#', $url ?? '', $m) ? $m[1] : null;

        if ($user->avatar && $path = $urlToPath($user->avatar)) {
            $toDelete[] = $path;
        }
        foreach ($user->resources as $resource) {
            foreach (['thumbnail', 'file_url', 'og_image'] as $field) {
                if ($resource->$field && $path = $urlToPath($resource->$field)) {
                    $toDelete[] = $path;
                }
            }
        }

        $user->forceDelete();

        if ($toDelete) {
            Storage::disk('public')->delete($toDelete);
        }

        return response()->json(["message" => "User and all associated content permanently deleted."]);
    }
    public function orders(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        return response()->json(["data" => $user->orders()->with("items")->latest()->get()]);
    }

    public function enrollments(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        return response()->json(["data" => $user->enrollments()->with("course")->get()]);
    }

    public function extendSubscription(Request $request, string $userId, string $subId): JsonResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:3650']]);

        $sub = \App\Models\UserPlanSubscription::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->findOrFail($subId);

        $base = ($sub->expires_at && $sub->expires_at->isFuture())
            ? $sub->expires_at
            : now();

        $sub->update([
            'expires_at' => $base->addDays($data['days']),
            'status'     => 'active',
        ]);

        return response()->json(['data' => $sub->fresh()]);
    }

    public function challengeEnrollments(string $id): JsonResponse
    {
        $user = User::withoutGlobalScope('company')->findOrFail($id);

        $enrollments = ChallengeEnrollment::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->with(['challenge:id,title,slug,thumbnail,badge_image,total_days'])
            ->latest()
            ->get()
            ->map(function ($enrollment) use ($user) {
                $challenge = $enrollment->challenge;
                if (!$challenge) return $enrollment->toArray();

                $dayIds   = ChallengeDay::where('challenge_id', $challenge->id)->pluck('id');
                $progress = ChallengeDayProgress::where('user_id', $user->id)
                    ->whereIn('challenge_day_id', $dayIds)
                    ->with('day:id,day_number,title,challenge_id')
                    ->get()
                    ->map(fn($p) => [
                        'day_number'   => $p->day?->day_number,
                        'day_title'    => $p->day?->title,
                        'completed_at' => $p->completed_at,
                        'mood'         => $p->mood,
                    ])
                    ->sortBy('day_number')
                    ->values();

                $completedDays = $progress->whereNotNull('completed_at')->count();

                return array_merge($enrollment->toArray(), [
                    'completed_days'   => $completedDays,
                    'total_days'       => $challenge->total_days,
                    'progress_percent' => $challenge->total_days > 0
                        ? (int) round($completedDays / $challenge->total_days * 100)
                        : 0,
                    'day_progress'     => $progress,
                ]);
            });

        return response()->json(['data' => $enrollments]);
    }

    public function resendEmailVerification(string $id): JsonResponse
    {
        $user = User::withoutGlobalScope('company')->findOrFail($id);

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'This user has already verified their email.'], 422);
        }

        $token = Str::random(64);
        $user->forceFill(['remember_token' => $token])->save();

        $company = $user->company;
        if ($company) {
            $scheme      = app()->environment('local') ? 'http' : 'https';
            $frontendUrl = rtrim($scheme . '://' . $company->domain, '/');
        } else {
            $frontendUrl = rtrim(config('app.frontend_url'), '/');
        }

        $verificationUrl = $frontendUrl . '/verify-email?token=' . $token . '&email=' . urlencode($user->email);
        $siteName        = Setting::getValue('site_name') ?? config('app.name');

        EmailService::send($user->email, 'email_verification', [
            '{username}'         => $user->name,
            '{email}'            => $user->email,
            '{verification_url}' => $verificationUrl,
            '{site_name}'        => $siteName,
        ]);

        return response()->json(['message' => "Verification email sent to {$user->email}."]);
    }

    public function sampleCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers_import_template.csv"',
        ];

        return response()->stream(function () {
            $out = fopen('php://output', 'w');
            // password is optional — leave blank to auto-generate
            // interests: pipe-separated values e.g. meditation|yoga|breathing
            // experience_level: beginner | intermediate | experienced
            fputcsv($out, ['name', 'email', 'password', 'phone', 'experience_level', 'interests']);
            fputcsv($out, ['Jane Smith',  'jane@example.com',  '',             '+1234567890', 'beginner',     'meditation|yoga']);
            fputcsv($out, ['John Doe',    'john@example.com',  '',             '',            'intermediate', 'breathing|mindfulness']);
            fputcsv($out, ['Alice Brown', 'alice@example.com', 'MyOwnPass1!', '+4478901234', 'experienced',  'yoga|stress-relief']);
            fclose($out);
        }, 200, $headers);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path   = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        $header = array_map('trim', fgetcsv($handle));

        foreach (['name', 'email'] as $col) {
            if (!in_array($col, $header)) {
                fclose($handle);
                return response()->json(['message' => "Missing required column: {$col}."], 422);
            }
        }

        $validLevels = ['beginner', 'intermediate', 'experienced'];
        $imported    = 0;
        $failed      = 0;
        $skipped     = 0;
        $errors      = [];
        $row         = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;
            if (count(array_filter($line)) === 0) continue;

            $data     = array_combine($header, array_pad($line, count($header), ''));
            $name     = trim($data['name']     ?? '');
            $email    = strtolower(trim($data['email']    ?? ''));
            $password = trim($data['password'] ?? '');
            $phone    = trim($data['phone']    ?? '') ?: null;
            $level    = trim($data['experience_level'] ?? '');
            $interests = trim($data['interests'] ?? '');

            // Auto-generate password if not provided
            $plainPassword = $password !== '' ? $password : \Illuminate\Support\Str::random(10) . '!A1';

            // Silently skip rows where both name and email are blank
            if (!$name && !$email) continue;

            if (!$name) {
                $errors[] = ['row' => $row, 'email' => $email, 'reason' => 'Name is required.'];
                $failed++;
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $row, 'email' => $email, 'reason' => 'Invalid email address.'];
                $failed++;
                continue;
            }
            if (strlen($plainPassword) < 8) {
                $errors[] = ['row' => $row, 'email' => $email, 'reason' => 'Password must be at least 8 characters.'];
                $failed++;
                continue;
            }
            if (User::withoutGlobalScope('company')->where('email', $email)->exists()) {
                $skipped++;
                continue;
            }

            // Parse interests from pipe-separated string
            $interestsArray = $interests !== ''
                ? array_values(array_filter(array_map('trim', explode('|', $interests))))
                : null;

            // Falls back to DB default ('beginner') when blank or unrecognised
            $experienceLevel = in_array($level, $validLevels) ? $level : 'beginner';

            try {
                $user = User::create([
                    'name'             => $name,
                    'email'            => $email,
                    'password'         => bcrypt($plainPassword),
                    'phone'            => $phone,
                    'experience_level' => $experienceLevel,
                    'interests'        => $interestsArray,
                    'source'           => 'csv_import',
                ]);
                \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update([
                    'email_verified_at' => now(),
                ]);
                $user->assignRole('user');

                // Queue welcome email — the scheduled command sends it within 1 minute
                \Illuminate\Support\Facades\DB::table('pending_welcome_emails')->insert([
                    'user_id'        => $user->id,
                    'plain_password' => $plainPassword,
                    'created_at'     => now(),
                ]);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = ['row' => $row, 'email' => $email, 'reason' => 'Server error: ' . $e->getMessage()];
                $failed++;
            }
        }

        fclose($handle);

        return response()->json([
            'imported' => $imported,
            'skipped'  => $skipped,
            'failed'   => $failed,
            'errors'   => $errors,
        ]);
    }

}