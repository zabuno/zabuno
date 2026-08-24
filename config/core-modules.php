<?php

declare(strict_types=1);

/**
 * CORE-05 Module Registry — data-only bootstrap for the finite Core Kernel
 * (docs/04 §2, §5; docs/32). Stage 1 scope: this is a static manifest
 * registry, not a lifecycle engine (install/enable/disable UI is Stage 2,
 * docs/26 S2-WP01). Every entry declares deterministic_baseline='required'
 * (docs/32: "61/61 modülde sabittir") and a valid ai_posture — AI-off/
 * zero-credit never breaks the module's core function.
 *
 * dependencies lists only other CORE-xx codes registered in this same file
 * (docs/03 ADR-L05: cross-module contracts only); a module's dependency on a
 * non-Core module (e.g. CORE-04 on Pricing/Subscription/Billing) is recorded
 * in its own modules/*.md spec, not duplicated here.
 */
return [
    'CORE-01' => [
        'name' => 'Identity & Sessions',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => [],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'advisory',
    ],
    'CORE-02' => [
        'name' => 'Tenancy/Organization/Venue',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-01'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'advisory',
    ],
    'CORE-03' => [
        'name' => 'Authorization',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-01', 'CORE-02'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'advisory',
    ],
    'CORE-04' => [
        'name' => 'Entitlements & Usage Metering',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-02'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'advisory',
    ],
    'CORE-05' => [
        'name' => 'Module Registry',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => [],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'advisory',
    ],
    'CORE-06' => [
        'name' => 'Settings/Secrets/Integrations',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-02'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'advisory',
    ],
    'CORE-07' => [
        'name' => 'Audit/Event Outbox',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-01', 'CORE-02'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'advisory',
    ],
    'CORE-08' => [
        'name' => 'Localization',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => [],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'assistive',
    ],
    'CORE-09' => [
        'name' => 'Taxonomy',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-02'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'assistive',
    ],
    'CORE-10' => [
        'name' => 'Workflow/State Machine',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-07'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'advisory',
    ],
    'CORE-11' => [
        'name' => 'ECA Rules',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-07', 'CORE-10'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'agentic_guarded',
    ],
    'CORE-12' => [
        'name' => 'Money/Ledger',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => [],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'advisory',
    ],
    'CORE-13' => [
        'name' => 'File/Media',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-02', 'CORE-04'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'automated_guarded',
    ],
    'CORE-14' => [
        'name' => 'Notifications',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-06'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'assistive',
    ],
    'CORE-15' => [
        'name' => 'Data Lifecycle',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-02', 'CORE-07'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'advisory',
    ],
    'CORE-16' => [
        'name' => 'Legal Records',
        'version' => '1.0.0',
        'module_class' => 'core',
        'dependencies' => ['CORE-01', 'CORE-07'],
        'deterministic_baseline' => 'required',
        'ai_posture' => 'assistive',
    ],
];
