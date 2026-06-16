<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Override Sanctum's token model to bypass the BelongsToCompany global scope
 * when resolving the authenticated user from a token.
 *
 * Without this, users with company_id = null (global admins) cannot be resolved
 * because the global scope filters to a specific company_id.
 */
class PersonalAccessToken extends SanctumToken
{
    public function tokenable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('tokenable')->withoutGlobalScopes();
    }
}
