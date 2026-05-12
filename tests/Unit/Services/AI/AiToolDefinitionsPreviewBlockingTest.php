<?php

declare(strict_types=1);

use App\Services\AI\AiToolDefinitions;
use App\Services\AI\XaiToolDefinitions;

/**
 * G-4-b M-7 — Verifies that AI tool definitions exclude write-class tools
 * when the caller is in preview mode. The PreviewWriteInterceptor middleware
 * excludes /api/ai-chat/conversations from its write-blocking sweep on the
 * grounds that "tool executor handles write blocking" — this test pins that
 * invariant down so a regression in the tool definition catalog cannot
 * silently let preview users write data through AI chat.
 *
 * The HasAiChat trait calls $toolDefinitions->getTools($user->is_preview_user),
 * so as long as the boolean flag reaches the tool list and the list correctly
 * filters writes, the invariant holds end-to-end. This test pins the tool list.
 */

describe('AiToolDefinitions preview blocking (G-4-b M-7)', function () {
    it('excludes write tools when isPreviewMode is true (Anthropic)', function () {
        $defs = new AiToolDefinitions;
        $previewTools = $defs->getTools(isPreviewMode: true);

        $toolNames = array_map(fn ($t) => $t['name'] ?? null, $previewTools);

        // Write tools that MUST NOT appear in preview mode
        $forbidden = [
            'create_what_if_scenario',
            'create_goal',
            'create_life_event',
            'create_savings_account',
            'create_investment_account',
            'create_pension',
            'create_property',
            'create_mortgage',
            'create_protection_policy',
            'create_asset',
        ];

        foreach ($forbidden as $tool) {
            expect($toolNames)->not()->toContain($tool, "Preview-mode tool list must not include write tool '{$tool}'");
        }
    });

    it('includes write tools when isPreviewMode is false (Anthropic)', function () {
        $defs = new AiToolDefinitions;
        $fullTools = $defs->getTools(isPreviewMode: false);

        $toolNames = array_map(fn ($t) => $t['name'] ?? null, $fullTools);

        // At least one representative write tool from each excluded group
        expect($toolNames)->toContain('create_savings_account');
        expect($toolNames)->toContain('create_what_if_scenario');
        expect($toolNames)->toContain('create_goal');
    });

    it('excludes write tools when isPreviewMode is true (xAI)', function () {
        $defs = new XaiToolDefinitions;
        $previewTools = $defs->getTools(isPreviewMode: true);

        // xAI wraps tools in {type: function, function: {name, ...}}; navigate the shape
        $extractName = function (array $tool): ?string {
            return $tool['function']['name'] ?? $tool['name'] ?? null;
        };
        $toolNames = array_map($extractName, $previewTools);

        $forbidden = [
            'create_what_if_scenario',
            'create_savings_account',
            'create_pension',
            'create_property',
        ];

        foreach ($forbidden as $tool) {
            expect($toolNames)->not()->toContain($tool, "xAI preview-mode tool list must not include write tool '{$tool}'");
        }
    });

    it('preview-mode tool list is strictly smaller than full tool list', function () {
        $defs = new AiToolDefinitions;
        expect(count($defs->getTools(isPreviewMode: true)))
            ->toBeLessThan(count($defs->getTools(isPreviewMode: false)));
    });
});
