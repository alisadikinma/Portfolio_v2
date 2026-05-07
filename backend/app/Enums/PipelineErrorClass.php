<?php

namespace App\Enums;

/**
 * Classification of pipeline failures used by retry deciders + per-class
 * Sonnet rewrite branches. Single source of truth for both LinkedIn and
 * Content Engine retry crons + carousel slide tier progression.
 *
 * See `App\Services\PipelineErrorClassifier` for the substring matching
 * priority order that maps an error string to one of these cases.
 */
enum PipelineErrorClass: string
{
    case Transient = 'transient';
    case DeterministicLlm = 'deterministic_llm';
    case PolicyPerson = 'policy_person';
    case PolicyMinor = 'policy_minor';
    case PolicyNsfw = 'policy_nsfw';
    case PolicyBrand = 'policy_brand';
    case PolicyGeneric = 'policy_generic';
    case Permanent = 'permanent';
    case Unknown = 'unknown';
}
