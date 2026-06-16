<?php

namespace App\Services;

use App\Models\Company;

class TenantContext
{
    private ?Company $company     = null;
    private bool     $isSuperAdmin = false;

    public function setCompany(?Company $company): void
    {
        $this->company = $company;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setIsSuperAdmin(bool $value): void
    {
        $this->isSuperAdmin = $value;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function companyId(): ?int
    {
        return $this->company?->id;
    }

    /**
     * Returns true when the super admin has NOT selected a specific company.
     * In this state, all global-scope filters are bypassed (sees all data).
     */
    public function isGlobal(): bool
    {
        return $this->isSuperAdmin && $this->company === null;
    }
}
