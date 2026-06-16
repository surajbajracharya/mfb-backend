<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Controller;
use App\Models\ConsentTemplate;
use App\Traits\HasTrash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminConsentTemplateController extends Controller
{
    use HasTrash;
    protected string $model = ConsentTemplate::class;
    protected array $trashedWith = ['company:id,name'];
    public function index(): JsonResponse
    {
        return response()->json(['data' => ConsentTemplate::with('company:id,name')->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'disclaimer_html'       => ['nullable', 'string'],
            'fields'                => ['nullable', 'array'],
            'fields.*.id'           => ['required_with:fields', 'string'],
            'fields.*.type'         => ['required_with:fields', 'string', 'in:text,textarea,date,time,radio,checkbox,checkbox_group,heading,paragraph'],
            'fields.*.label'        => ['required_with:fields', 'string'],
            'fields.*.required'     => ['sometimes', 'boolean'],
            'fields.*.placeholder'  => ['sometimes', 'nullable', 'string'],
            'fields.*.help_text'    => ['sometimes', 'nullable', 'string'],
            'fields.*.options'      => ['sometimes', 'nullable', 'array'],
            'fields.*.options.*'    => ['string'],
        ]);
        return response()->json(ConsentTemplate::create($data), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $template = ConsentTemplate::findOrFail($id);
        $data = $request->validate([
            'name'                  => ['sometimes', 'string', 'max:255'],
            'disclaimer_html'       => ['nullable', 'string'],
            'fields'                => ['nullable', 'array'],
            'fields.*.id'           => ['required_with:fields', 'string'],
            'fields.*.type'         => ['required_with:fields', 'string', 'in:text,textarea,date,time,radio,checkbox,checkbox_group,heading,paragraph'],
            'fields.*.label'        => ['required_with:fields', 'string'],
            'fields.*.required'     => ['sometimes', 'boolean'],
            'fields.*.placeholder'  => ['sometimes', 'nullable', 'string'],
            'fields.*.help_text'    => ['sometimes', 'nullable', 'string'],
            'fields.*.options'      => ['sometimes', 'nullable', 'array'],
            'fields.*.options.*'    => ['string'],
        ]);
        $template->update($data);
        return response()->json($template);
    }

    public function destroy(string $id): JsonResponse
    {
        $template = ConsentTemplate::findOrFail($id);
        $template->appointmentTypes()->update(['consent_template_id' => null]);
        $template->delete();
        return response()->json(['message' => 'Consent template deleted.']);
    }
}
