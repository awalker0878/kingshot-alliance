<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Enums;

enum EvidenceLifecycleStatus: string
{
    case Uploaded = 'uploaded';
    case Classifying = 'classifying';
    case Classified = 'classified';
    case Extracting = 'extracting';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';
    case Committing = 'committing';
    case Committed = 'committed';
    case Unsupported = 'unsupported';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
    case DeletePending = 'delete_pending';
    case Deleted = 'deleted';
    case PurgePending = 'purge_pending';
    case Purged = 'purged';
}
