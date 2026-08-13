<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ContentExperienceTest extends TestCase
{
    public function test_member_content_pages_use_shared_shell_and_real_routes(): void
    {
        $index = $this->read('resources/js/pages/Alliance/ContentIndex.vue');
        $detail = $this->read('resources/js/pages/Alliance/ContentDetail.vue');
        $manage = $this->read('resources/js/pages/Alliance/ContentManage.vue');

        foreach ([$index, $detail, $manage] as $source) {
            self::assertStringContainsString('AppLayout', $source);
            self::assertStringContainsString(':has-active-alliance="true"', $source);
            self::assertStringNotContainsString('<main', $source);
            self::assertStringContainsString('<h1', $source);
            self::assertStringNotContainsString('v-html', $source);
        }

        foreach ([
            '/alliance/content',
            '/alliance/content/manage',
            '/alliance/content/${item.slug}',
            '/alliance/public-profile',
            '/alliance/content/categories',
            '/alliance/content/${editingId.value}',
            '/alliance/content/${id}/publish',
            '/revisions/${revision.id}/restore',
            '/alliance/media',
            '/alliance/media/${asset.id}',
        ] as $contract) {
            self::assertStringContainsString($contract, $index.$detail.$manage);
        }
    }

    public function test_content_visuals_use_only_recorded_publishing_state(): void
    {
        $source = $this->read('resources/js/pages/Alliance/ContentIndex.vue')
            .$this->read('resources/js/pages/Alliance/ContentDetail.vue')
            .$this->read('resources/js/pages/Alliance/ContentManage.vue');

        foreach ([
            'Content Performance',
            'Most popular',
            'AI summary',
            'AI writer',
            'AI recommendations',
            'social share count',
            'engagement rate',
            'conversion rate',
        ] as $invented) {
            self::assertStringNotContainsString($invented, $source);
        }
    }

    public function test_content_catalogue_covers_all_supported_locales(): void
    {
        $source = $this->read('resources/js/localization/messages/content/en.ts');

        foreach ([
            'en',
            'ar',
            'de',
            'es',
            'fr',
            'id',
            'it',
            'ja',
            'ko',
            'pl',
            'pt-BR',
            'ru',
            'th',
            'tr',
            'vi',
            'zh-CN',
            'zh-TW',
        ] as $locale) {
            self::assertStringContainsString("'".$locale."'", $source);
        }

        foreach ([
            'hubTitle',
            'manageContent',
            'publicProfile',
            'createContent',
            'mediaLibrary',
            'contentInventory',
        ] as $key) {
            self::assertStringContainsString($key.':', $source);
        }
    }

    public function test_content_controllers_expose_authenticated_shell_identity_without_new_domain_state(): void
    {
        foreach ([
            'app/Domain/Content/Http/Controllers/MemberContentController.php',
            'app/Domain/Content/Http/Controllers/ContentManagementController.php',
        ] as $path) {
            $source = $this->read($path);
            self::assertStringContainsString("'user' => [", $source);
            self::assertStringContainsString("'name' => (string)", $source);
            self::assertStringContainsString("'email' => (string)", $source);
        }

        $manager = $this->read('app/Domain/Content/Http/Controllers/ContentManagementController.php');
        self::assertStringContainsString('PermissionKey::ContentManage', $manager);
        self::assertStringContainsString("'scheduled_for' => ['nullable', 'date']", $manager);
        self::assertStringContainsString('RestoreContentRevision', $manager);
        self::assertStringContainsString('UploadMediaAsset', $manager);
    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source);

        return $source;
    }
}
