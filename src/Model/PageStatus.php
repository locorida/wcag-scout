<?php

declare(strict_types=1);

namespace WcagScout\Model;

enum PageStatus: string
{
    /** Loaded and checked. */
    case Audited = 'audited';
    /** Not loaded on purpose, e.g. disallowed by robots.txt or outside the site. */
    case Skipped = 'skipped';
    /** Loading or checking failed (timeout, HTTP error, browser crash). */
    case Failed = 'failed';
}
