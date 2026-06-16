<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class Setting extends Model {
    use BelongsToCompany;

    protected $fillable = ['key', 'value', 'company_id'];

    private const PUBLIC_KEYS = [
        'site_name','site_tagline','site_description','timezone','language',
        'maintenance_mode',
        'header_scripts','footer_scripts',
        'primary_color','secondary_color','accent_color','font_family',
        'page_banner_image','page_banner_overlay',
        'footer_bg_image','footer_bg_overlay',
        'pagination_courses','pagination_events','pagination_resources','pagination_appointments',
        'footer_text','custom_css',
        'footer_cta_label','footer_cta_heading','footer_cta_btn_text','footer_cta_btn_link',
        'footer_top_links','footer_made_with_text','footer_newsletter_code',
        'heading_font','heading_font_weight',
        'heading_font_size_h1','heading_font_size_h2','heading_font_size_h3',
        'heading_letter_spacing','heading_text_transform',
        'body_font_size','body_font_weight','body_line_height','body_letter_spacing',
        'company_name','company_logo','company_favicon','footer_logo','company_email',
        'company_phone','company_address','company_website',
        'social_facebook','social_twitter','social_instagram',
        'social_youtube','social_linkedin','copyright_text',
        // Payment gateway — enabled flags + public keys + logos (no secrets)
        'pg_stripe_enabled','pg_stripe_key','pg_stripe_mode','pg_stripe_logo',
        'pg_paypal_enabled','pg_paypal_client_id','pg_paypal_mode','pg_paypal_logo',
        // Home page sections
        'hero_images','hero_overlay_color','hero_overlay_opacity',
        'hero_badge_text','hero_title','hero_subtitle','hero_cta1_text','hero_cta1_link','hero_cta2_text','hero_cta2_link',
        'hero_proof_images','hero_proof_text',
        'hp_sections_order',
        'hp_hero_slider_on','hp_hero_text_on','hp_services_on','hp_about_on',
        'hp_offerings_on','hp_stats_on','hp_specs_on','hp_testimonials_on','hp_cta_on','hp_resources_on',
        'hp_services_template','hp_services','hp_services_color1','hp_services_color2','hp_about_heading','hp_about_sub','hp_about_text',
        'hp_offerings_template',
        'hp_offerings','hp_offerings_sub','hp_offerings_heading','hp_stats_template','hp_stats','hp_specializations','hp_specs_bg_image',
        'hp_specs_overlay_color','hp_specs_overlay_opacity','hp_specs_card_image',
        'hp_specs_sub','hp_specs_heading','hp_specs_stat_value','hp_specs_stat_label',
        'hp_testimonials_template','hp_testimonials',
        'hp_cta_bg_image','hp_cta_overlay_opacity',
        'hp_cta_sub_label','hp_cta_title','hp_cta_subtitle','hp_cta_btn_text','hp_cta_btn_link',
        'hp_resources_sub','hp_resources_heading',
        'hp_courses_on','hp_courses_template','hp_courses_sub','hp_courses_heading','hp_courses_count',
        'hp_challenges_on','hp_challenges_template','hp_challenges_sub','hp_challenges_heading','hp_challenges_count',
        'hp_resources_count',
        'hp_marquee_on','hp_marquee_items','hp_marquee_color1','hp_marquee_color2',
        'hp_about_image1','hp_about_image2','hp_about_video_url',
        'hp_about_btn1_text','hp_about_btn1_link','hp_about_btn2_text',
        'hp_about_template',
        'hp_cta_template',
        'footer_template',
        // Pricing page
        'plans_template',
        'pricing_badge_text','pricing_hero_title','pricing_hero_highlight','pricing_hero_subtitle',
        'pricing_trust_1_icon','pricing_trust_1_text',
        'pricing_trust_2_icon','pricing_trust_2_text',
        'pricing_trust_3_icon','pricing_trust_3_text',
        'pricing_disclaimer',
        // ID Verification page
        'idverify_page_title','idverify_subtitle',
        'idverify_why_title','idverify_why_body',
        'idverify_instructions_title','idverify_instructions',
        'idverify_submit_label','idverify_footer_note',
        // SEO — archive & global
        'seo_default_title','seo_default_description','seo_default_og_image',
        'seo_home_title','seo_home_description','seo_home_og_image',
        'seo_courses_title','seo_courses_description','seo_courses_og_image',
        'seo_events_title','seo_events_description','seo_events_og_image',
        'seo_plans_title','seo_plans_description','seo_plans_og_image',
        'seo_resources_title','seo_resources_description','seo_resources_og_image',
        'seo_appointments_title','seo_appointments_description','seo_appointments_og_image',
    ];

    public static function getValue(string $key, mixed $default = null): mixed {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, mixed $value): void {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getAll(): array {
        // Global (company_id = null) as base, company-specific overrides on top
        $global = static::withoutGlobalScope('company')
            ->whereNull('company_id')
            ->get()->pluck('value', 'key')->toArray();

        $company = static::all()->pluck('value', 'key')->toArray();

        return array_merge($global, $company);
    }

    public static function getPublic(): array {
        // Global (company_id = null) as base, company-specific overrides on top
        $global = static::withoutGlobalScope('company')
            ->whereIn('key', self::PUBLIC_KEYS)
            ->whereNull('company_id')
            ->get()->pluck('value', 'key')->toArray();

        $company = static::withoutGlobalScope('company')
            ->whereIn('key', self::PUBLIC_KEYS)
            ->whereNotNull('company_id')
            ->where(function ($q) {
                try {
                    $id = app(\App\Services\TenantContext::class)->companyId();
                    $q->where('company_id', $id);
                } catch (\Throwable) {}
            })
            ->get()->pluck('value', 'key')->toArray();

        return array_merge($global, $company);
    }
}
