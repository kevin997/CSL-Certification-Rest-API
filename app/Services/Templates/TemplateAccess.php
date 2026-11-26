<?php

namespace App\Services\Templates;

use App\Models\PurchasedTemplate;
use App\Models\Template;
use App\Models\User;

/**
 * Who may read a template's content and who may write into it.
 *
 * Mirror the marketplace rules already used by the duplicate endpoint: the
 * creator, a public template, or a purchased template may be copied from;
 * only the creator may modify or extend a template.
 */
class TemplateAccess
{
    public function canRead(Template $template, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ((int) $template->created_by === (int) $user->id) {
            return true;
        }

        if ((bool) $template->is_public) {
            return true;
        }

        return PurchasedTemplate::query()
            ->where('user_id', $user->id)
            ->where('template_id', $template->id)
            ->exists();
    }

    public function canWrite(Template $template, ?User $user): bool
    {
        return $user !== null && (int) $template->created_by === (int) $user->id;
    }
}
