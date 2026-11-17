<?php

namespace App\Policies;

use App\Models\{Announcement, User};
use Illuminate\Auth\Access\Response;

class AnnouncementPolicy
{
    public function update(User $actor, Announcement $announcement): Response
    {
        return $announcement->isPublished()
            ? Response::deny(__('policy.announcement.published'))
            : Response::allow();
    }

    public function publish(User $actor, Announcement $announcement): Response
    {
        return $this->update($actor, $announcement);
    }
}
