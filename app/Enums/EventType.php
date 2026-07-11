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
}
