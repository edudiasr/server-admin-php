<?php

declare(strict_types=1);

namespace Admin\Security;

enum LoginResult
{
    case Ok;
    case NeedsTotp;
    case Invalid;
    case Locked;
}
