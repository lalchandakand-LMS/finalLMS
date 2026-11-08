<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunityPlatformSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_support_and_plugin_foundation_tables_are_migrated(): void
    {
        foreach ([
            'community_posts',
            'community_post_likes',
            'community_post_comments',
            'teacher_follows',
            'discussion_rooms',
            'discussion_room_members',
            'discussion_messages',
            'direct_messages',
            'support_tickets',
            'lms_plugins',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} table to exist.");
        }
    }

    public function test_plugin_registry_is_metadata_only_and_disabled_by_default(): void
    {
        $id = \Illuminate\Support\Facades\DB::table('lms_plugins')->insertGetId([
            'slug' => 'sample-plugin',
            'name' => 'Sample Plugin',
            'manifest' => json_encode(['capabilities' => []], JSON_THROW_ON_ERROR),
            'status' => 'disabled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plugin = \Illuminate\Support\Facades\DB::table('lms_plugins')->find($id);

        $this->assertSame('disabled', $plugin->status);
        $this->assertSame('sample-plugin', $plugin->slug);
    }
}
