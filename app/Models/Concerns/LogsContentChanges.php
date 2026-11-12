<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * CMS guard rail: every create, edit and delete of admin-written content is
 * written to the audit log (Admin → System → Audit log) with who did it and
 * the old and new values. Used by Banner, Page, HomeSection and HelpArticle.
 */
trait LogsContentChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            // A shopper reading a help article bumps `views`; that is not an edit.
            ->logExcept(['views'])
            ->dontLogIfAttributesChangedOnly(['views'])
            // Translated fields as stored (every language), not just the admin's current language.
            ->useAttributeRawValues($this->translatable ?? [])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
