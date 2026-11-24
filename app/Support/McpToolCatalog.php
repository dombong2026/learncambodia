<?php

namespace App\Support;

/**
 * Read-only inventory of proposed MCP capabilities.
 *
 * The catalog is documentation for administrators. No entry is an enabled
 * remote action, a token grant, or a registered MCP tool at this stage.
 */
final class McpToolCatalog
{
    public static function tools(): array
    {
        return [
            ['name' => 'site.health.read', 'label' => 'Site Health', 'domain' => 'Operations',
                'scope' => 'site:read', 'mode' => 'Read', 'risk' => 'Low',
                'summary' => 'Inspect a redacted uptime and application health summary.'],
            ['name' => 'courses.list', 'label' => 'Course Catalog', 'domain' => 'Learning',
                'scope' => 'courses:read', 'mode' => 'Read', 'risk' => 'Low',
                'summary' => 'List approved courses and their public learning metadata.'],
            ['name' => 'campaigns.list', 'label' => 'Homepage Campaigns', 'domain' => 'Campaigns',
                'scope' => 'campaigns:read', 'mode' => 'Read', 'risk' => 'Low',
                'summary' => 'Inspect published campaigns without private quiz answer keys.'],
            ['name' => 'editorial.list', 'label' => 'Editorial Inventory', 'domain' => 'Content',
                'scope' => 'editorial:read', 'mode' => 'Read', 'risk' => 'Low',
                'summary' => 'List pages, posts, policies and help articles by status.'],
            ['name' => 'campaigns.draft.create', 'label' => 'Create Campaign Draft', 'domain' => 'Campaigns',
                'scope' => 'campaigns:write', 'mode' => 'Write', 'risk' => 'Medium',
                'summary' => 'Create an unpublished campaign draft with explicit change preview.'],
            ['name' => 'campaigns.publish', 'label' => 'Publish Campaign', 'domain' => 'Campaigns',
                'scope' => 'campaigns:publish', 'mode' => 'Publish', 'risk' => 'High',
                'summary' => 'Publish a reviewed campaign after fresh administrator approval.'],
            ['name' => 'courses.draft.update', 'label' => 'Update Course Draft', 'domain' => 'Learning',
                'scope' => 'courses:write', 'mode' => 'Write', 'risk' => 'Medium',
                'summary' => 'Edit course and lesson drafts without bypassing academic review.'],
            ['name' => 'assessments.draft.update', 'label' => 'Update Quiz / Exam Draft', 'domain' => 'Learning',
                'scope' => 'assessments:write', 'mode' => 'Write', 'risk' => 'Medium',
                'summary' => 'Edit question banks and feedback; never expose correct keys to learners.'],
            ['name' => 'support.tickets.list', 'label' => 'Support Tickets', 'domain' => 'Support',
                'scope' => 'support:read', 'mode' => 'Read', 'risk' => 'High',
                'summary' => 'Read authorized support tickets only; private messages require strict role checks.'],
            ['name' => 'users.directory.read', 'label' => 'User Directory', 'domain' => 'Accounts',
                'scope' => 'users:read', 'mode' => 'Read', 'risk' => 'High',
                'summary' => 'Inspect limited account metadata with privacy redaction and audit logging.'],
            ['name' => 'settings.configuration.read', 'label' => 'Platform Configuration', 'domain' => 'Operations',
                'scope' => 'settings:read', 'mode' => 'Read', 'risk' => 'High',
                'summary' => 'Inspect non-secret configuration status; never reveal credentials.'],
            ['name' => 'settings.configuration.update', 'label' => 'Change Platform Configuration', 'domain' => 'Operations',
                'scope' => 'settings:write', 'mode' => 'Write', 'risk' => 'Critical',
                'summary' => 'Reserved for separately approved superadministrator workflows.'],
        ];
    }

    public static function capabilities(): array
    {
        return [
            'Dashboard & Operations' => 'Health, public settings status, deployment acceptance and audits',
            'Learning & Assessments' => 'Courses, modules, lessons, quiz/exam drafts and academic review',
            'Content & Campaigns' => 'Pages, posts, policies, homepage campaigns and publication',
            'Users & Support' => 'Scoped users and tickets with privacy-aware role checks',
            'Integrations & Storage' => 'Connection status and storage health; no secret disclosure',
        ];
    }
}
