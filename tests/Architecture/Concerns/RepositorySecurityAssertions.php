<?php

declare(strict_types=1);

namespace Tests\Architecture\Concerns;

trait RepositorySecurityAssertions
{
    public function test_historical_domain_security_profiles_are_preserved_until_p10(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/app/Domain');

        foreach ($this->historicalDomainDocumentation() as $domain) {
            $path = $this->root().'/docs/domains/'.$domain.'/security/README.md';

            self::assertFileExists($path, sprintf('Missing historical domain security profile: docs/domains/%s/security/README.md', $domain));

            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertStringContainsString('**Primary security boundary:**', $contents, $this->relativePath($path));
            self::assertStringContainsString('../README.md', $contents, $this->relativePath($path));
            self::assertStringContainsString('../../../security/security-baseline.md', $contents, $this->relativePath($path));
        }
    }

    public function test_required_dcp_p2_focused_security_reviews_exist_and_are_indexed(): void
    {
        foreach ($this->requiredDcpP2FocusedSecurityReviews() as $domain => $files) {
            $profilePath = $this->root().'/docs/domains/'.$domain.'/security/README.md';
            $profile = file_get_contents($profilePath);
            self::assertIsString($profile);

            foreach ($files as $file) {
                $path = $this->root().'/docs/domains/'.$domain.'/security/'.$file;
                self::assertFileExists($path, sprintf('Missing DCP-P2 focused security review: docs/domains/%s/security/%s', $domain, $file));
                self::assertStringContainsString($file, $profile, sprintf('Domain security profile must index focused review: docs/domains/%s/security/%s', $domain, $file));
            }
        }
    }

    public function test_dcp_p2_focused_security_reviews_follow_the_security_documentation_standard(): void
    {
        foreach ($this->requiredDcpP2FocusedSecurityReviews() as $domain => $files) {
            foreach ($files as $file) {
                $path = $this->root().'/docs/domains/'.$domain.'/security/'.$file;
                $contents = file_get_contents($path);

                self::assertIsString($contents);
                self::assertStringContainsString('**Document type:** Living capability security review', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Status:** Current', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Owning domain:**', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Capability:**', $contents, $this->relativePath($path));

                $this->assertHeadingsAppearInOrder($contents, [
                    '## 1. Scope and security objective',
                    '## 2. Assets and sensitive data',
                    '## 3. Trust boundaries',
                    '## 4. Threats and controls',
                    '## 5. Authorization, tenancy and privacy',
                    '## 6. Integrity, replay and concurrency',
                    '## 7. Secret and data lifecycle',
                    '## 8. Abuse limits and failure behavior',
                    '## 9. Verification and evidence',
                    '## 10. Residual risks and external controls',
                ], $path);
            }
        }
    }

    public function test_dcp_p2_living_security_reviews_stay_with_the_owning_domain(): void
    {
        foreach ($this->requiredDcpP2FocusedSecurityReviews() as $files) {
            foreach ($files as $file) {
                self::assertFileDoesNotExist(
                    $this->root().'/docs/security/'.$file,
                    sprintf('Domain-specific living security review belongs under its owning documentation area: %s', $file),
                );
            }
        }
    }
}
