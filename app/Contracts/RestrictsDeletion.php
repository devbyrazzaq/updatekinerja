<?php

namespace App\Contracts;

interface RestrictsDeletion
{
    /**
     * Return a human-readable reason why this record cannot be deleted,
     * or null when deletion is allowed.
     */
    public function getDeletionRestrictionReason(): ?string;
}
