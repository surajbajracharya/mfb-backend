<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\GuestAuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseSectionController;
use App\Http\Controllers\Api\CourseLectureController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\CourseProgressController;
use App\Http\Controllers\Api\CourseReviewController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\AppointmentTypeController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventTicketController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminCourseController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\UploadController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\Admin\AdminCertificateController;
use App\Http\Controllers\Api\Admin\AdminEmailController;
use App\Http\Controllers\Api\Admin\AdminMenuController;
use App\Http\Controllers\Api\Admin\AdminSettingsController;
use App\Http\Controllers\Api\Admin\AdminAvailabilityController;
use App\Http\Controllers\Api\Admin\AdminConsentTemplateController;
use App\Http\Controllers\Api\Admin\AdminAppointmentCategoryController;
use App\Http\Controllers\Api\Admin\AdminEventCategoryController;
use App\Http\Controllers\Api\SuperAdmin\CompanyController;
use App\Http\Controllers\Api\SuperAdmin\RoleController;
use App\Http\Controllers\Api\PaypalController;
use App\Http\Controllers\Api\IdentityVerificationController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\Admin\AdminChallengeController;
use App\Http\Controllers\Api\Admin\LandingPageController;

Route::prefix('v1')->middleware(['tenant', 'company_timezone'])->group(function () {

    Route::get('/', fn () => response()->json([
        'app'     => (\App\Models\Setting::getValue('site_name') ?? config('app.name')) . ' API',
        'version' => '1.0.0',
        'status'  => 'ok',
    ]));

    // Webhook - no auth, Stripe needs raw body
    Route::post('webhooks/stripe', [WebhookController::class, 'handleStripe'])
         ->name('webhooks.stripe');

    // PayPal IPN webhook - no auth required
    Route::post('webhooks/paypal', [PaypalController::class, 'webhook'])
         ->name('webhooks.paypal');

    // --------------------------------------------------
    // Public Auth Routes
    // --------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('register',        [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login',           [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('admin-login',     [AuthController::class, 'adminLogin'])->middleware('throttle:5,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
        Route::post('reset-password',  [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
        Route::post('verify-email',    [AuthController::class, 'verifyEmail'])->middleware('throttle:10,1');
        Route::post('guest',           [GuestAuthController::class, 'guestCheckout'])->middleware('throttle:10,1');
    });

    // --------------------------------------------------
    // Public Routes
    // --------------------------------------------------
    Route::get('categories',              [CategoryController::class, 'index']);
    Route::get('categories/{slug}',       [CategoryController::class, 'show']);

    Route::get('courses',                 [CourseController::class, 'index']);
    Route::get('courses/{slug}',          [CourseController::class, 'show']);

    Route::get('plans',                   [PlanController::class, 'index']);
    Route::get('plans/{id}',              [PlanController::class, 'show']);

    Route::get('appointment-types',       [AppointmentTypeController::class, 'index']);
    Route::get('appointment-types/{slug}', [AppointmentTypeController::class, 'show']);
    Route::get('appointment-slots',       [AppointmentController::class, 'availableSlots']);

    Route::get('events',                  [EventController::class, 'index']);
    Route::get('events/{slug}',           [EventController::class, 'show']);

    Route::get('resources',               [ResourceController::class, 'index']);
    Route::get('resources/{slug}',        [ResourceController::class, 'show']);

    Route::get('pages',                   [PageController::class, 'index']);
    Route::get('pages/{slug}',            [PageController::class, 'show']);

    // Public landing pages (slug-based, published only)
    Route::get('lp',                     [LandingPageController::class, 'publicIndex']);
    Route::get('lp/{slug}',              [LandingPageController::class, 'publicShow']);

    // Public menu by display location (e.g. "Primary", "Footer")
    Route::get('menus/location/{location}', function (\Illuminate\Http\Request $request, $location) {
        $companyId = $request->header('X-Company-ID');
        $menu = \App\Models\Menu::where('location', $location)
            ->where(function ($q) use ($companyId) {
                if ($companyId) $q->where('company_id', $companyId)->orWhereNull('company_id');
                else $q->whereNull('company_id');
            })
            ->first();
        if (!$menu) return response()->json(['name' => '', 'items' => []]);
        $items = \App\Models\MenuItem::where('menu_id', $menu->id)->orderBy('sort_order')->get()->toArray();
        $build = function (array $all, ?int $pid = null) use (&$build): array {
            return array_values(array_filter(array_map(function ($item) use (&$build, $all, $pid) {
                if ((int)($item['parent_id'] ?? 0) !== (int)($pid ?? 0)) return null;
                $item['children'] = $build($all, (int)$item['id']);
                return $item;
            }, $all)));
        };
        return response()->json(['name' => $menu->name, 'items' => $build($items)]);
    });
    // Public menu by ID (used for sidebar menus on pages)
    Route::get('menus/{id}/items', function ($id) {
        $menu  = \App\Models\Menu::find($id);
        $items = \App\Models\MenuItem::where('menu_id', $id)->orderBy('sort_order')->get()->toArray();
        $build = function (array $all, ?int $pid = null) use (&$build): array {
            return array_values(array_filter(array_map(function ($item) use (&$build, $all, $pid) {
                if ((int)($item['parent_id'] ?? 0) !== (int)($pid ?? 0)) return null;
                $item['children'] = $build($all, (int)$item['id']);
                return $item;
            }, $all)));
        };
        return response()->json(['name' => $menu?->name ?? '', 'items' => $build($items)]);
    });
    Route::post('resources/{slug}/download', [ResourceController::class, 'download']);

    // Challenges — public
    Route::get('challenges', [ChallengeController::class, 'index']);
    // my-enrollments MUST come before {slug} to avoid wildcard capture
    Route::middleware('auth:sanctum')->get('challenges/my-enrollments', [ChallengeController::class, 'myEnrollments']);
    Route::get('challenges/{slug}', [ChallengeController::class, 'show']);

    // Course reviews — public read
    Route::get('courses/{course}/reviews', [CourseReviewController::class, 'index']);

    // Settings (public — non-sensitive keys only)
    // Resolves company from X-Company-ID header so public sites get the right branding
    // Identity verification (one-time token, no auth required — user may be deactivated)
    Route::get('identity-verify/{token}',  [IdentityVerificationController::class, 'showForm']);
    Route::post('identity-verify/{token}', [IdentityVerificationController::class, 'submit']);

    // Public user profiles (respects privacy preferences)
    Route::get('users/{username}/profile', [AuthController::class, 'publicProfile']);

    Route::get('settings/public', function (\Illuminate\Http\Request $request) {
        $companyId = $request->header('X-Company-ID');
        $company   = null;
        if ($companyId) {
            $company = \App\Models\Company::find($companyId);
            if ($company) {
                app(\App\Services\TenantContext::class)->setCompany($company);
            }
        }
        $data = \App\Models\Setting::getPublic();
        $data['modules_enabled'] = $company
            ? ($company->modules_enabled ?? ['courses', 'events', 'appointments', 'resources'])
            : ['courses', 'events', 'appointments', 'resources'];
        return response()->json(['data' => $data]);
    });

    // --------------------------------------------------
    // Authenticated Routes
    // --------------------------------------------------
    Route::middleware(['auth:sanctum', 'super_switch'])->group(function () {

        // Auth (account management)
        Route::post('auth/logout',   [AuthController::class, 'logout']);
        Route::get('auth/me',         [AuthController::class, 'me']);
        Route::put('auth/profile',    [AuthController::class, 'updateProfile']);
        Route::put('auth/password',                  [AuthController::class, 'changePassword']);
        Route::post('auth/avatar',                   [AuthController::class, 'uploadAvatar']);
        Route::post('auth/request-account-deletion', [AuthController::class, 'requestAccountDeletion']);

        // Identity verification (user self-service)
        Route::get('auth/verification',        [IdentityVerificationController::class, 'myStatus']);
        Route::post('auth/verification/submit', [IdentityVerificationController::class, 'selfSubmit']);

        // Notification preferences
        Route::get('auth/notification-preferences',  [AuthController::class, 'getNotificationPreferences']);
        Route::put('auth/notification-preferences',  [AuthController::class, 'updateNotificationPreferences']);

        // Privacy preferences
        Route::get('auth/privacy-preferences',  [AuthController::class, 'getPrivacyPreferences']);
        Route::put('auth/privacy-preferences',  [AuthController::class, 'updatePrivacyPreferences']);

        // Dashboard
        Route::get('dashboard',      [DashboardController::class, 'index']);

        // Course player (private, enrolled users only)
        Route::get('courses/{slug}/player', [CourseController::class, 'player']);

        // Enrollments
        Route::get('enrollments',         [EnrollmentController::class, 'index']);
        Route::get('enrollments/{id}',    [EnrollmentController::class, 'show']);
        Route::post('courses/{id}/enroll',[EnrollmentController::class, 'enrollFree']);

        // Progress tracking
        Route::get('progress',                       [CourseProgressController::class, 'index']);
        Route::post('progress',                      [CourseProgressController::class, 'store']);
        Route::post('courses/progress',              [CourseProgressController::class, 'store']);
        Route::get('courses/{courseId}/progress',    [CourseProgressController::class, 'courseProgress']);

        // Lecture notes
        Route::get('notes',                  [CourseProgressController::class, 'notes']);
        Route::post('lectures/{id}/notes',   [CourseProgressController::class, 'storeNote']);
        Route::put('notes/{id}',             [CourseProgressController::class, 'updateNote']);
        Route::delete('notes/{id}',          [CourseProgressController::class, 'deleteNote']);

        // Certificates (user)
        Route::get('certificates',                           [CertificateController::class, 'index']);
        Route::get('certificates/{number}/download',         [CertificateController::class, 'download']);

        // Reviews
        Route::apiResource('courses.reviews', CourseReviewController::class)
             ->only(['index', 'store', 'update', 'destroy']);

        // Orders
        Route::get('orders',      [OrderController::class, 'index']);
        Route::get('orders/{id}', [OrderController::class, 'show']);

        // Checkout & Billing (Stripe)
        Route::post('checkout/session',              [CheckoutController::class, 'createSession']);
        Route::post('checkout',                      [CheckoutController::class, 'createSession']);
        Route::post('checkout/free',                 [CheckoutController::class, 'freeCheckout']);
        Route::post('checkout/verify',               [CheckoutController::class, 'success']);
        Route::get('checkout/success',               [CheckoutController::class, 'success']);
        Route::get('checkout/cancel',                [CheckoutController::class, 'cancel']);
        Route::get('billing/portal',                 [CheckoutController::class, 'billingPortal']);
        // Stripe direct card payment (Payment Intents)
        Route::post('checkout/stripe/intent',        [CheckoutController::class, 'createPaymentIntent']);
        Route::post('checkout/stripe/intent-confirm',[CheckoutController::class, 'confirmPaymentIntent']);

        // Checkout & Billing (PayPal)
        Route::post('checkout/paypal/session', [PaypalController::class, 'createSession']);
        Route::post('checkout/paypal/capture', [PaypalController::class, 'captureOrder']);

        // Appointments (user)
        Route::get('appointments',                          [AppointmentController::class, 'index']);
        Route::get('appointments/{id}',                    [AppointmentController::class, 'show']);
        Route::post('appointments',                         [AppointmentController::class, 'store']);
        Route::post('appointments/schedule-order',          [AppointmentController::class, 'scheduleOrder']);
        Route::put('appointments/{id}/cancel',              [AppointmentController::class, 'cancel']);
        Route::patch('appointments/{id}/cancel',            [AppointmentController::class, 'cancel']);
        Route::get('appointments/{id}/my-consent-pdf',      [AppointmentController::class, 'myConsentPdf']);

        // Challenges (user)
        Route::get('challenges/my-enrollments',                                        [ChallengeController::class, 'myEnrollments']);
        Route::post('challenges/{id}/enroll',                                          [ChallengeController::class, 'enroll']);
        Route::post('challenges/{challengeId}/days/{dayId}/complete',                  [ChallengeController::class, 'completeDay']);
        Route::post('challenges/{challengeId}/days/{dayId}/mood',                     [ChallengeController::class, 'saveMood']);

        // Event Tickets (user)
        Route::get('event-tickets',               [EventTicketController::class, 'index']);
        Route::get('event-tickets/{id}',          [EventTicketController::class, 'show']);
        Route::get('event-tickets/{id}/download', [EventTicketController::class, 'download']);

        // --------------------------------------------------
        // Admin Routes
        // --------------------------------------------------
        Route::prefix('admin')->middleware('role_or_permission:admin|super_admin|access admin panel')->group(function () {

            // Shared utilities (admin panel access sufficient — no specific module permission)
            Route::post('uploads/pdf',    [UploadController::class, 'uploadPdf']);
            Route::delete('uploads/pdf',  [UploadController::class, 'deletePdf']);
            Route::post('uploads/audio',  [UploadController::class, 'uploadAudio']);
            Route::delete('uploads/audio',[UploadController::class, 'deleteAudio']);
            Route::post('uploads/image',  [UploadController::class, 'uploadImage']);
            Route::delete('uploads/image',[UploadController::class, 'deleteImage']);
            Route::get('dashboard',       [AdminDashboardController::class, 'index']);
            Route::get('stats',           [AdminDashboardController::class, 'stats']);
            // Companies dropdown — needed by all admins for filter UIs
            Route::get('companies', fn () => response()->json([
                'data' => \App\Models\Company::select('id', 'name')->orderBy('name')->get(),
            ]));

            // ── Categories ────────────────────────────────────────────────────────
            Route::middleware('permission:view categories')->group(function () {
                Route::get('categories',             [CategoryController::class, 'adminIndex']);
                Route::get('categories/trashed',     [CategoryController::class, 'trashed']);
            });
            Route::middleware('permission:create categories')->group(function () {
                Route::post('categories',            [CategoryController::class, 'store']);
                Route::post('categories/reorder',    [CategoryController::class, 'reorder']);
                Route::post('categories/{id}/restore', [CategoryController::class, 'restore']);
            });
            Route::middleware('permission:edit categories')->group(function () {
                Route::put('categories/{category}',  [CategoryController::class, 'update']);
            });
            Route::middleware('permission:delete categories')->group(function () {
                Route::delete('categories/{category}',  [CategoryController::class, 'destroy']);
                Route::delete('categories/{id}/force',  [CategoryController::class, 'forceDelete']);
                Route::delete('categories/empty-trash', [CategoryController::class, 'emptyTrash']);
            });

            // ── Courses ───────────────────────────────────────────────────────────
            Route::middleware('permission:view courses')->group(function () {
                Route::get('courses',           [AdminCourseController::class, 'index']);
                Route::get('courses/trashed',   [AdminCourseController::class, 'trashed']);
                Route::get('courses/{course}',  [AdminCourseController::class, 'show']);
            });
            Route::middleware('permission:create courses')->group(function () {
                Route::post('courses',                  [AdminCourseController::class, 'store']);
                Route::post('courses/reorder',          [AdminCourseController::class, 'reorder']);
                Route::post('courses/{id}/duplicate',   [AdminCourseController::class, 'duplicate']);
            });
            Route::middleware('permission:edit courses')->group(function () {
                Route::put('courses/{course}',          [AdminCourseController::class, 'update']);
                Route::post('courses/{id}/restore',     [AdminCourseController::class, 'restore']);
                // Sections & Lectures are part of course editing
                Route::apiResource('courses.sections', CourseSectionController::class)->except(['index', 'show']);
                Route::apiResource('sections.lectures', CourseLectureController::class)->except(['index', 'show']);
            });
            Route::middleware('permission:delete courses')->group(function () {
                Route::delete('courses/{course}',       [AdminCourseController::class, 'destroy']);
                Route::delete('courses/{id}/force',     [AdminCourseController::class, 'forceDelete']);
                Route::delete('courses/empty-trash',    [AdminCourseController::class, 'emptyTrash']);
            });
            Route::middleware('permission:publish courses')->group(function () {
                Route::post('courses/{id}/publish',     [AdminCourseController::class, 'publish']);
                Route::post('courses/{id}/archive',     [AdminCourseController::class, 'archive']);
            });

            // ── Certificates ──────────────────────────────────────────────────────
            Route::middleware('permission:view certificates')->group(function () {
                Route::get('certificates',               [AdminCertificateController::class, 'index']);
                Route::get('certificates/{id}/download', [AdminCertificateController::class, 'download']);
                Route::get('certificate-template',       [AdminCertificateController::class, 'getTemplate']);
            });
            Route::middleware('permission:edit certificate-template')->group(function () {
                Route::put('certificate-template',       [AdminCertificateController::class, 'updateTemplate']);
            });

            // ── Reviews ───────────────────────────────────────────────────────────
            Route::middleware('permission:view reviews')->group(function () {
                Route::get('reviews',                    [CourseReviewController::class, 'adminAllReviews']);
                Route::get('reviews/trashed',            [CourseReviewController::class, 'trashed']);
                Route::get('courses/{courseId}/reviews', [CourseReviewController::class, 'adminIndex']);
            });
            Route::middleware('permission:moderate reviews')->group(function () {
                Route::post('reviews/{id}/approve',                      [CourseReviewController::class, 'approveGlobal']);
                Route::post('reviews/{id}/unapprove',                    [CourseReviewController::class, 'unapproveGlobal']);
                Route::post('reviews/{id}/reply',                        [CourseReviewController::class, 'reply']);
                Route::post('reviews/{id}/restore',                      [CourseReviewController::class, 'restore']);
                Route::post('courses/{courseId}/reviews/{id}/approve',   [CourseReviewController::class, 'approve']);
                Route::post('courses/{courseId}/reviews/{id}/unapprove', [CourseReviewController::class, 'unapprove']);
            });
            Route::middleware('permission:delete reviews')->group(function () {
                Route::delete('reviews/{id}',                   [CourseReviewController::class, 'adminDestroyGlobal']);
                Route::delete('reviews/{id}/force',             [CourseReviewController::class, 'forceDelete']);
                Route::delete('reviews/empty-trash',            [CourseReviewController::class, 'emptyTrash']);
                Route::delete('courses/{courseId}/reviews/{id}',[CourseReviewController::class, 'adminDestroy']);
            });

            // ── Plans ─────────────────────────────────────────────────────────────
            Route::middleware('permission:view plans')->group(function () {
                Route::get('plans',          [PlanController::class, 'adminIndex']);
                Route::get('plans/trashed',  [PlanController::class, 'trashed']);
                Route::get('plans/{id}',     [PlanController::class, 'adminShow']);
            });
            Route::middleware('permission:create plans')->group(function () {
                Route::post('plans',         [PlanController::class, 'store']);
                Route::post('plans/reorder', [PlanController::class, 'reorder']);
            });
            Route::middleware('permission:edit plans')->group(function () {
                Route::put('plans/{id}',             [PlanController::class, 'update']);
                Route::post('plans/{id}/sync-items', [PlanController::class, 'syncItems']);
                Route::post('plans/{id}/restore',    [PlanController::class, 'restore']);
            });
            Route::middleware('permission:delete plans')->group(function () {
                Route::delete('plans/{id}',          [PlanController::class, 'destroy']);
                Route::delete('plans/{id}/force',    [PlanController::class, 'forceDelete']);
                Route::delete('plans/empty-trash',   [PlanController::class, 'emptyTrash']);
            });

            // ── Menus ─────────────────────────────────────────────────────────────
            Route::middleware('permission:view menus')->group(function () {
                Route::get('menus',           [AdminMenuController::class, 'index']);
                Route::get('menus/available', [AdminMenuController::class, 'available']);
                Route::get('menus/{id}',      [AdminMenuController::class, 'show']);
            });
            Route::middleware('permission:create menus')->group(function () {
                Route::post('menus',          [AdminMenuController::class, 'store']);
            });
            Route::middleware('permission:edit menus')->group(function () {
                Route::put('menus/{id}',        [AdminMenuController::class, 'update']);
                Route::post('menus/{id}/items', [AdminMenuController::class, 'saveItems']);
            });
            Route::middleware('permission:delete menus')->group(function () {
                Route::delete('menus/{id}',   [AdminMenuController::class, 'destroy']);
            });

            // ── Orders ────────────────────────────────────────────────────────────
            Route::middleware('permission:view orders')->group(function () {
                Route::get('orders',      [OrderController::class, 'adminIndex']);
                Route::get('orders/{id}', [OrderController::class, 'adminShow']);
            });
            Route::middleware('permission:manage orders')->group(function () {
                Route::post('orders/{id}/cancel',    [OrderController::class, 'cancel']);
                Route::post('orders/{id}/mark-paid', [OrderController::class, 'markPaid']);
                Route::post('orders/{id}/flag',      [OrderController::class, 'flag']);
            });
            Route::middleware('permission:refund orders')->group(function () {
                Route::post('orders/{id}/refund',    [OrderController::class, 'refund']);
            });

            // ── Appointment Types ─────────────────────────────────────────────────
            Route::middleware('permission:view appointment-types')->group(function () {
                Route::get('appointment-types',         [AppointmentTypeController::class, 'adminIndex']);
                Route::get('appointment-types/trashed', [AppointmentTypeController::class, 'trashed']);
            });
            Route::middleware('permission:create appointment-types')->group(function () {
                Route::post('appointment-types',         [AppointmentTypeController::class, 'store']);
                Route::post('appointment-types/reorder', [AppointmentTypeController::class, 'reorder']);
            });
            Route::middleware('permission:edit appointment-types')->group(function () {
                Route::put('appointment-types/{appointment_type}',  [AppointmentTypeController::class, 'update']);
                Route::post('appointment-types/{id}/restore',       [AppointmentTypeController::class, 'restore']);
            });
            Route::middleware('permission:delete appointment-types')->group(function () {
                Route::delete('appointment-types/{appointment_type}', [AppointmentTypeController::class, 'destroy']);
                Route::delete('appointment-types/{id}/force',         [AppointmentTypeController::class, 'forceDelete']);
                Route::delete('appointment-types/empty-trash',        [AppointmentTypeController::class, 'emptyTrash']);
            });

            // ── Availability ──────────────────────────────────────────────────────
            Route::middleware('permission:manage availability')->group(function () {
                Route::get('availability/extent',        [AdminAvailabilityController::class, 'extent']);
                Route::get('availability/blocked-dates', [AdminAvailabilityController::class, 'blockedDates']);
                Route::delete('availability/bulk',       [AdminAvailabilityController::class, 'bulkDestroy']);
                Route::delete('availability/day',        [AdminAvailabilityController::class, 'destroyDay']);
                Route::apiResource('availability', AdminAvailabilityController::class)->except(['show']);
            });

            // ── Appointment Categories ────────────────────────────────────────────
            Route::get('appointment-categories',         [AdminAppointmentCategoryController::class, 'index']);
            Route::post('appointment-categories',        [AdminAppointmentCategoryController::class, 'store']);
            Route::delete('appointment-categories/{id}', [AdminAppointmentCategoryController::class, 'destroy']);

            // ── Event Categories ──────────────────────────────────────────────────
            Route::get('event-categories',         [AdminEventCategoryController::class, 'index']);
            Route::post('event-categories',        [AdminEventCategoryController::class, 'store']);
            Route::delete('event-categories/{id}', [AdminEventCategoryController::class, 'destroy']);

            // ── Consent Forms ─────────────────────────────────────────────────────
            Route::middleware('permission:view consent-forms')->group(function () {
                Route::get('consent-templates',         [AdminConsentTemplateController::class, 'index']);
                Route::get('consent-templates/trashed', [AdminConsentTemplateController::class, 'trashed']);
            });
            Route::middleware('permission:create consent-forms')->group(function () {
                Route::post('consent-templates',        [AdminConsentTemplateController::class, 'store']);
            });
            Route::middleware('permission:edit consent-forms')->group(function () {
                Route::put('consent-templates/{id}',            [AdminConsentTemplateController::class, 'update']);
                Route::post('consent-templates/{id}/restore',   [AdminConsentTemplateController::class, 'restore']);
            });
            Route::middleware('permission:delete consent-forms')->group(function () {
                Route::delete('consent-templates/{id}',         [AdminConsentTemplateController::class, 'destroy']);
                Route::delete('consent-templates/{id}/force',   [AdminConsentTemplateController::class, 'forceDelete']);
                Route::delete('consent-templates/empty-trash',  [AdminConsentTemplateController::class, 'emptyTrash']);
            });

            // ── Appointments ──────────────────────────────────────────────────────
            Route::middleware('permission:view appointments')->group(function () {
                Route::get('appointments',                 [AppointmentController::class, 'adminIndex']);
                Route::get('appointments/{id}/consent-pdf',[AppointmentController::class, 'downloadConsentPdf']);
            });
            Route::middleware('permission:manage appointments')->group(function () {
                Route::put('appointments/{id}',            [AppointmentController::class, 'update']);
                Route::patch('appointments/{id}',          [AppointmentController::class, 'update']);
                Route::post('appointments/{id}/confirm',   [AppointmentController::class, 'confirm']);
                Route::post('appointments/{id}/complete',  [AppointmentController::class, 'complete']);
                Route::post('appointments/{id}/cancel',    [AppointmentController::class, 'adminCancel']);
                Route::post('event-tickets/{id}/cancel',   [EventTicketController::class, 'adminCancel']);
                Route::post('appointment-slots/block',     [AppointmentController::class, 'blockSlot']);
            });

            // ── Events ────────────────────────────────────────────────────────────
            Route::middleware('permission:view events')->group(function () {
                Route::get('events',         [EventController::class, 'adminIndex']);
                Route::get('events/trashed', [EventController::class, 'trashed']);
            });
            Route::middleware('permission:create events')->group(function () {
                Route::post('events',        [EventController::class, 'store']);
            });
            Route::middleware('permission:edit events')->group(function () {
                Route::put('events/{event}',        [EventController::class, 'update']);
                Route::post('events/{id}/restore',  [EventController::class, 'restore']);
            });
            Route::middleware('permission:delete events')->group(function () {
                Route::delete('events/{event}',     [EventController::class, 'destroy']);
                Route::delete('events/{id}/force',  [EventController::class, 'forceDelete']);
                Route::delete('events/empty-trash', [EventController::class, 'emptyTrash']);
            });
            Route::middleware('permission:publish events')->group(function () {
                Route::post('events/{id}/publish',   [EventController::class, 'publish']);
                Route::post('events/{id}/unpublish', [EventController::class, 'unpublish']);
            });

            // ── Resources ─────────────────────────────────────────────────────────
            Route::middleware('permission:view resources')->group(function () {
                Route::get('resources',         [ResourceController::class, 'adminIndex']);
                Route::get('resources/trashed', [ResourceController::class, 'trashed']);
            });
            Route::middleware('permission:create resources')->group(function () {
                Route::post('resources',         [ResourceController::class, 'store']);
                Route::post('resources/reorder', [ResourceController::class, 'reorder']);
            });
            Route::middleware('permission:edit resources')->group(function () {
                Route::put('resources/{resource}',      [ResourceController::class, 'update']);
                Route::post('resources/{id}/restore',   [ResourceController::class, 'restore']);
                Route::post('resources/{id}/publish',  [ResourceController::class, 'publish']);
                Route::post('resources/{id}/archive',  [ResourceController::class, 'archive']);
            });
            Route::middleware('permission:delete resources')->group(function () {
                Route::delete('resources/{resource}',   [ResourceController::class, 'destroy']);
                Route::delete('resources/{id}/force',   [ResourceController::class, 'forceDelete']);
                Route::delete('resources/empty-trash',  [ResourceController::class, 'emptyTrash']);
            });

            // ── Pages ─────────────────────────────────────────────────────────────
            Route::middleware('permission:view pages')->group(function () {
                Route::get('pages',          [PageController::class, 'adminIndex']);
                Route::get('pages/trashed',  [PageController::class, 'trashed']);
                Route::get('pages/{id}',     [PageController::class, 'adminShow']);
            });
            Route::middleware('permission:create pages')->group(function () {
                Route::post('pages',         [PageController::class, 'store']);
            });
            Route::middleware('permission:edit pages')->group(function () {
                Route::put('pages/{id}',            [PageController::class, 'update']);
                Route::post('pages/{id}/restore',   [PageController::class, 'restore']);
            });
            Route::middleware('permission:delete pages')->group(function () {
                Route::delete('pages/{id}',         [PageController::class, 'destroy']);
                Route::delete('pages/{id}/force',   [PageController::class, 'forceDelete']);
                Route::delete('pages/empty-trash',  [PageController::class, 'emptyTrash']);
            });

            // ── Email Templates ───────────────────────────────────────────────────
            Route::middleware('permission:view email-templates')->group(function () {
                Route::get('email-templates',                   [AdminEmailController::class, 'templates']);
                Route::get('email-templates/{id}/preview',      [AdminEmailController::class, 'previewTemplate']);
                Route::get('email-settings',                    [AdminEmailController::class, 'getSettings']);
            });
            Route::middleware('permission:edit email-templates')->group(function () {
                Route::put('email-templates/{id}',              [AdminEmailController::class, 'updateTemplate']);
                Route::post('email-templates/{id}/send-test',   [AdminEmailController::class, 'sendTest']);
                Route::put('email-settings',                    [AdminEmailController::class, 'updateSettings']);
            });

            // ── Users ─────────────────────────────────────────────────────────────
            Route::middleware('permission:view users')->group(function () {
                Route::get('users',                            [AdminUserController::class, 'index']);
                Route::get('users/bin',                        [AdminUserController::class, 'bin']);
                Route::get('users/sample-csv',                 [AdminUserController::class, 'sampleCsv']);
                Route::get('users/{user}',                     [AdminUserController::class, 'show']);
                Route::get('users/{id}/orders',                [AdminUserController::class, 'orders']);
                Route::get('users/{id}/enrollments',           [AdminUserController::class, 'enrollments']);
                Route::get('users/{id}/challenge-enrollments', [AdminUserController::class, 'challengeEnrollments']);
                Route::get('identity-verify/{id}/document',    [IdentityVerificationController::class, 'downloadDocument']);
            });
            Route::middleware('permission:create users')->group(function () {
                Route::post('users',                           [AdminUserController::class, 'store']);
                Route::post('users/import-csv',                [AdminUserController::class, 'importCsv']);
            });
            Route::middleware('permission:edit users')->group(function () {
                Route::put('users/{user}',                                       [AdminUserController::class, 'update']);
                Route::post('users/{id}/restore',                                [AdminUserController::class, 'restore']);
                Route::post('users/{id}/send-verification',                      [IdentityVerificationController::class, 'sendLink']);
                Route::post('users/{id}/approve-verification',                   [IdentityVerificationController::class, 'adminApprove']);
                Route::post('users/{id}/revoke-verification',                    [IdentityVerificationController::class, 'adminRevoke']);
                Route::post('users/{userId}/plan-subscriptions/{subId}/extend',  [AdminUserController::class, 'extendSubscription']);
            });
            Route::middleware('permission:delete users')->group(function () {
                Route::delete('users/{user}',       [AdminUserController::class, 'destroy']);
                Route::delete('users/{id}/force',   [AdminUserController::class, 'forceDestroy']);
            });

            // ── Challenges ────────────────────────────────────────────────────────
            Route::middleware('permission:view challenges')->group(function () {
                Route::get('challenges',                     [AdminChallengeController::class, 'index']);
                Route::get('challenges/{id}',                [AdminChallengeController::class, 'show']);
                Route::get('challenges/{challengeId}/days',  [AdminChallengeController::class, 'getDays']);
            });
            Route::middleware('permission:create challenges')->group(function () {
                Route::post('challenges',                              [AdminChallengeController::class, 'store']);
                Route::post('challenges/reorder',                      [AdminChallengeController::class, 'reorder']);
                Route::post('challenges/{challengeId}/days',           [AdminChallengeController::class, 'storeDay']);
                Route::post('challenges/{challengeId}/days/reorder',   [AdminChallengeController::class, 'reorderDays']);
            });
            Route::middleware('permission:edit challenges')->group(function () {
                Route::put('challenges/{id}',                          [AdminChallengeController::class, 'update']);
                Route::post('challenges/{id}/publish',                 [AdminChallengeController::class, 'publish']);
                Route::post('challenges/{id}/unpublish',               [AdminChallengeController::class, 'unpublish']);
                Route::put('challenges/{challengeId}/days/{dayId}',    [AdminChallengeController::class, 'updateDay']);
            });
            Route::middleware('permission:delete challenges')->group(function () {
                Route::delete('challenges/{id}',                       [AdminChallengeController::class, 'destroy']);
                Route::delete('challenges/{challengeId}/days/{dayId}', [AdminChallengeController::class, 'destroyDay']);
            });

            // ── Landing Pages ─────────────────────────────────────────────────────
            Route::middleware('permission:view landing-pages')->group(function () {
                Route::get('landing-pages',          [LandingPageController::class, 'adminIndex']);
                Route::get('landing-pages/trashed',  [LandingPageController::class, 'trashed']);
                Route::get('landing-pages/{id}',     [LandingPageController::class, 'show']);
            });
            Route::middleware('permission:create landing-pages')->group(function () {
                Route::post('landing-pages',         [LandingPageController::class, 'store']);
            });
            Route::middleware('permission:edit landing-pages')->group(function () {
                Route::put('landing-pages/{id}',           [LandingPageController::class, 'update']);
                Route::post('landing-pages/{id}/restore',  [LandingPageController::class, 'restore']);
            });
            Route::middleware('permission:delete landing-pages')->group(function () {
                Route::delete('landing-pages/{id}',         [LandingPageController::class, 'destroy']);
                Route::delete('landing-pages/{id}/force',   [LandingPageController::class, 'forceDelete']);
                Route::delete('landing-pages/empty-trash',  [LandingPageController::class, 'emptyTrash']);
            });
            Route::middleware('permission:publish landing-pages')->group(function () {
                Route::post('landing-pages/{id}/publish',   [LandingPageController::class, 'publish']);
            });

            // ── Settings ──────────────────────────────────────────────────────────
            Route::middleware('permission:view settings')->group(function () {
                Route::get('settings',            [AdminSettingsController::class, 'index']);
            });
            Route::middleware('permission:edit settings')->group(function () {
                Route::put('settings',            [AdminSettingsController::class, 'update']);
                Route::post('settings/test-smtp', [AdminSettingsController::class, 'testSmtp']);
            });
        });

        // ── Super Admin Only ─────────────────────────────────────────────────
        Route::prefix('superadmin')->middleware('role:super_admin')->group(function () {
            Route::apiResource('companies', CompanyController::class);
            Route::post('companies/{company}/reactivate', [CompanyController::class, 'reactivate']);
            Route::apiResource('roles', RoleController::class)->except(['show']);
            Route::get('permissions-grouped', [RoleController::class, 'permissionsGrouped']);
        });
    });
});