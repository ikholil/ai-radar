<?php

namespace App\Enums;

enum EventType: string
{
    case Release = 'release';
    case Launch = 'launch';
    case Paper = 'paper';
    case StarMilestone = 'star_milestone';
    case CommunityPost = 'community_post';
    case BlogPost = 'blog_post';

    public function label(): string
    {
        return match ($this) {
            self::Release => 'Release',
            self::Launch => 'Launch',
            self::Paper => 'Paper',
            self::StarMilestone => 'Stars',
            self::CommunityPost => 'Community',
            self::BlogPost => 'Blog',
        };
    }

    /**
     * Dashboard filter chip this event type belongs to.
     */
    public function category(): string
    {
        return match ($this) {
            self::Release => 'models',
            self::Paper => 'papers',
            self::Launch, self::StarMilestone => 'tools',
            self::CommunityPost, self::BlogPost => 'community',
        };
    }

    public function colorClasses(): string
    {
        return match ($this) {
            self::Release => 'text-emerald-400 border-emerald-400/40 bg-emerald-400/10',
            self::Launch => 'text-amber-400 border-amber-400/40 bg-amber-400/10',
            self::Paper => 'text-sky-400 border-sky-400/40 bg-sky-400/10',
            self::StarMilestone => 'text-yellow-400 border-yellow-400/40 bg-yellow-400/10',
            self::CommunityPost => 'text-fuchsia-400 border-fuchsia-400/40 bg-fuchsia-400/10',
            self::BlogPost => 'text-blue-400 border-blue-400/40 bg-blue-400/10',
        };
    }
}
