<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Architecture\Concerns\RepositoryDocumentationLinkAssertions;
use Tests\Architecture\Concerns\RepositoryDocumentationRootAssertions;
use Tests\Architecture\Concerns\RepositoryDomainDocumentationAssertions;
use Tests\Architecture\Concerns\RepositoryKingdomAssertions;
use Tests\Architecture\Concerns\RepositorySecurityAssertions;
use Tests\Architecture\Concerns\RepositoryStructureSupport;
use Tests\Architecture\Concerns\RepositoryTestGroupAssertions;

final class RepositoryStructureTest extends TestCase
{
    use RepositoryDocumentationLinkAssertions;
    use RepositoryDocumentationRootAssertions;
    use RepositoryDomainDocumentationAssertions;
    use RepositoryKingdomAssertions;
    use RepositorySecurityAssertions;
    use RepositoryStructureSupport;
    use RepositoryTestGroupAssertions;
}
