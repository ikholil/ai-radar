<?php

namespace App\Enums;

enum SubjectKind: string
{
    case Model = 'model';
    case Repo = 'repo';
    case Tool = 'tool';
    case Paper = 'paper';
    case None = 'none';
}
