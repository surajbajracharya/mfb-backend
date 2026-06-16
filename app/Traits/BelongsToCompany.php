<?php

namespace App\Traits;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        // Auto-inject company_id on creation (only if not explicitly set)
        static::creating(function ($model) {
            if (empty($model->company_id)) {
                try {
                    $model->company_id = app(TenantContext::class)->companyId();
                } catch (\Throwable) {
                    // Container not ready (CLI, migrations) — leave null
                }
            }
        });

        // Global query scope — auto-filter to current company on every query
        static::addGlobalScope('company', function (Builder $builder) {
            try {
                $ctx = app(TenantContext::class);
            } catch (\Throwable) {
                // Container not ready (CLI, migrations) — skip scoping
                return;
            }

            // Super admin with no company selected: bypass scope (see all data)
            if ($ctx->isGlobal()) {
                return;
            }

            $builder->where(
                $builder->getModel()->getTable() . '.company_id',
                $ctx->companyId()
            );
        });
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    /**
     * Start a query that bypasses the company scope.
     * Use this only in super-admin or system contexts.
     */
    public static function withoutCompanyScope(): Builder
    {
        return static::withoutGlobalScope('company');
    }
}
