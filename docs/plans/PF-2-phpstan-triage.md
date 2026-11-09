# PF-2 PHPStan baseline triage

Phase 0E. Source: `backend/phpstan-baseline.neon` (level 5), analysed with the baseline removed to get line numbers.

## Result

- Baseline before Phase 0E: 582 entries / 826 occurrences.
- Baseline after: 576 entries / 817 occurrences (only shrinks).
- **BUG: 4 groups fixed** (variable.undefined x6 in ContentIdeaController, env() outside config x2, undefined `ArticleGenerationService::trigger()` in ProcessScheduledIdeas). Each has a failing-first test and its baseline entry is removed.
- **NOISE: every remaining non-`property.notFound` entry** (listed below). `property.notFound` (336 entries / 476 occurrences) are Eloquent magic attributes and `Post::$translations`-style relation properties: NOISE, one summary line, not listed.

## Fixed (BUG)

| file:line | identifier | BUG/NOISE | reason | fix commit |
|---|---|---|---|---|
| app/Http/Controllers/Api/Admin/ContentIdeaController.php (generateSegmentImage) | variable.undefined x6 | BUG | Unknown `segment_index` left `$variationIndex`/`$reuseIndex`/`$priorSlot` unset: paid GeminiGen job queued, then 500 "Undefined variable". Now 422 before any work. Test: GenerateSegmentImageUnknownSegmentTest | 4de3666c |
| app/Http/Controllers/Api/ContactController.php:217 | larastan.noEnvCallsOutsideOfConfig | BUG | env() returns null under config:cache; fell back to admin@example.com. Now config('mail.admin_email'), skip + warning when unset. Test: ContactAdminEmailTest | 5c334a28 |
| app/Http/Controllers/Api/LinkedInOAuthController.php:82 | larastan.noEnvCallsOutsideOfConfig | BUG | env() null under config:cache: OAuth callback redirected to http://localhost:5173. Now config('app.frontend_url'). Test: LinkedInOAuthCallbackRedirectTest | 5c334a28 |
| app/Console/Commands/ProcessScheduledIdeas.php:48 | method.notFound | BUG | Called nonexistent ArticleGenerationService::trigger(); every due scheduled idea went researching -> exception -> back to draft each tick, so scheduled ideas never started. Now uses the same pipeline selection as startResearch. Test: ProcessScheduledIdeasTriggerTest | 1cef2b2a |

## Remaining (NOISE) by identifier

- larastan.relationExistence: 102
- nullCoalesce.offset: 80
- nullsafe.neverNull: 52
- method.notFound: 23
- argument.type: 22
- function.alreadyNarrowedType: 11
- if.alwaysTrue: 8
- identical.alwaysFalse: 7
- argument.unresolvableType: 5
- nullCoalesce.expr: 5
- property.nonObject: 4
- nullCoalesce.variable: 3
- method.nonObject: 2
- return.type: 2
- identical.alwaysTrue: 2
- deadCode.unreachable: 2
- ternary.alwaysTrue: 2
- method.unused: 1
- greater.alwaysFalse: 1
- match.unhandled: 1
- arrayValues.list: 1
- parameterByRef.unusedType: 1
- return.unusedType: 1
- booleanNot.alwaysTrue: 1
- smaller.alwaysTrue: 1
- notIdentical.alwaysTrue: 1
- trait.unused: 1

## Per-entry table

| file:line | identifier | BUG/NOISE | reason | fix commit |
|---|---|---|---|---|
| app/Console/Commands/BackfillLinkedInLinkComment.php:56 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Console/Commands/FlagStalePosts.php:44 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Console/Commands/FlagStalePosts.php:97 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Console/Commands/PollRebrandAssets.php:308 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Console/Commands/PollRebrandAssets.php:419 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Console/Commands/PollRebrandAssets.php:419 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Console/Commands/ProcessPendingImages.php:59 | identical.alwaysFalse | NOISE | Larastan derives type enum from the first migration; later migration widened it to include carousel_slide. | - |
| app/Console/Commands/ProcessPendingImages.php:93 | identical.alwaysFalse | NOISE | Larastan derives type enum from the first migration; later migration widened it to include carousel_slide. | - |
| app/Console/Commands/ProcessPendingImages.php:134 | identical.alwaysFalse | NOISE | Larastan derives type enum from the first migration; later migration widened it to include carousel_slide. | - |
| app/Console/Commands/ProcessPendingImages.php:167 | identical.alwaysFalse | NOISE | Larastan derives type enum from the first migration; later migration widened it to include carousel_slide. | - |
| app/Console/Commands/ProcessPendingTranslations.php:36 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Console/Commands/PromptScheduleReadyDrafts.php:148 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Console/Commands/PurgeLowViralityLinkedInDrafts.php:82 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Console/Commands/ReapUnclaimedPostizJobs.php:95 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Console/Commands/ReapUnclaimedPostizJobs.php:132 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Console/Commands/RegenerateNonPublishedCrossPosts.php:90 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Console/Commands/RegenerateNonPublishedCrossPosts.php:92 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Console/Commands/ScanBlogForLinkedInConversion.php:50 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Console/Commands/ScanBlogForLinkedInConversion.php:66 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Console/Commands/ScanBlogForLinkedInConversion.php:74 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Console/Commands/ScanBlogForLinkedInConversion.php:74 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Console/Commands/SendWeeklyNewsletter.php:38 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Console/Commands/SendWeeklyNewsletter.php:38 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Enums/ContentIdeaStatus.php:51 | nullCoalesce.offset | NOISE | Defensive fallback for an enum transition map keyed by every case. | - |
| app/Enums/FacebookPostStatus.php:41 | nullCoalesce.offset | NOISE | Defensive fallback for an enum transition map keyed by every case. | - |
| app/Enums/InstagramPostStatus.php:55 | nullCoalesce.offset | NOISE | Defensive fallback for an enum transition map keyed by every case. | - |
| app/Enums/LinkedInPostStatus.php:56 | nullCoalesce.offset | NOISE | Defensive fallback for an enum transition map keyed by every case. | - |
| app/Enums/RedditPostStatus.php:33 | nullCoalesce.offset | NOISE | Defensive fallback for an enum transition map keyed by every case. | - |
| app/Enums/RepurposeJobStatus.php:94 | nullCoalesce.offset | NOISE | Defensive fallback for an enum transition map keyed by every case. | - |
| app/Enums/ThreadsPostStatus.php:33 | nullCoalesce.offset | NOISE | Defensive fallback for an enum transition map keyed by every case. | - |
| app/Enums/TiktokPostStatus.php:39 | nullCoalesce.offset | NOISE | Defensive fallback for an enum transition map keyed by every case. | - |
| app/Http/Controllers/Api/Admin/Concerns/HandlesCrossPostDraftActions.php:382 | argument.unresolvableType | NOISE | Closure return type unresolved by Larastan generics; map() works. | - |
| app/Http/Controllers/Api/Admin/Concerns/HandlesCrossPostDraftActions.php:382 | argument.unresolvableType | NOISE | Closure return type unresolved by Larastan generics; map() works. | - |
| app/Http/Controllers/Api/Admin/Concerns/HandlesCrossPostDraftActions.php:382 | argument.unresolvableType | NOISE | Closure return type unresolved by Larastan generics; map() works. | - |
| app/Http/Controllers/Api/Admin/ContentIdeaController.php:527 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Http/Controllers/Api/Admin/ContentIdeaController.php:1407 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/Admin/DashboardController.php:67 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/Admin/DashboardController.php:170 | nullCoalesce.expr | NOISE | Redundant ?? guard; harmless. | - |
| app/Http/Controllers/Api/Admin/DashboardController.php:182 | nullCoalesce.expr | NOISE | Redundant ?? guard; harmless. | - |
| app/Http/Controllers/Api/Admin/DashboardController.php:189 | nullCoalesce.expr | NOISE | Redundant ?? guard; harmless. | - |
| app/Http/Controllers/Api/Admin/DashboardController.php:234 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/Admin/DashboardController.php:315 | method.unused | NOISE | Static-analysis limitation; no runtime error. | - |
| app/Http/Controllers/Api/Admin/LinkedInDraftController.php:1544 | argument.unresolvableType | NOISE | Closure return type unresolved by Larastan generics; map() works. | - |
| app/Http/Controllers/Api/Admin/LinkedInDraftController.php:1545 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Controllers/Api/Admin/NewsletterAdminController.php:107 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/Admin/NewsletterAdminController.php:107 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/Admin/NewsletterAdminController.php:171 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/Admin/NewsletterAdminController.php:171 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/Admin/NewsletterAdminController.php:363 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/Admin/NewsletterAdminController.php:368 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Controllers/Api/Admin/RepurposeJobController.php:538 | argument.unresolvableType | NOISE | Closure return type unresolved by Larastan generics; map() works. | - |
| app/Http/Controllers/Api/Admin/RepurposeJobController.php:550 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Controllers/Api/Automation/PostizPublishController.php:88 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Controllers/Api/AutomationController.php:30 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AutomationController.php:106 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AutomationController.php:201 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AutomationController.php:201 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AutomationController.php:266 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AutomationController.php:400 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AutomationController.php:487 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AutomationController.php:660 | nullCoalesce.expr | NOISE | Redundant ?? guard; harmless. | - |
| app/Http/Controllers/Api/AwardController.php:20 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AwardController.php:94 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AwardController.php:197 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AwardController.php:265 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/AwardController.php:431 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/BlogPipelineController.php:47 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Controllers/Api/BlogPipelineController.php:48 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Controllers/Api/BlogPipelineController.php:162 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/BlogPipelineController.php:162 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/BlogPipelineController.php:162 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CategoryController.php:26 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CategoryController.php:40 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CategoryController.php:83 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CategoryController.php:105 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CategoryController.php:138 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CategoryController.php:181 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CategoryController.php:202 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CvExportController.php:124 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CvExportController.php:135 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CvExportController.php:135 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/CvExportController.php:220 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Http/Controllers/Api/GalleryController.php:25 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:25 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:77 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:86 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:113 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:113 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:159 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:221 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:221 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:252 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:381 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GalleryController.php:381 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GeoController.php:25 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/GeoController.php:93 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/HomepageController.php:70 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/HomepageController.php:81 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/HomepageController.php:81 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/LinkedInOAuthController.php:57 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Controllers/Api/PostController.php:29 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:29 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:29 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:36 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:56 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:107 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:107 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:107 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:187 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:187 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:187 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:219 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:219 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:219 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:244 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:257 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Controllers/Api/PostController.php:381 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:381 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:381 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:484 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:484 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/PostController.php:484 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/ProjectController.php:100 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/ProjectController.php:160 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/ProjectController.php:186 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/ProjectController.php:279 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/ProjectController.php:384 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/SettingsController.php:780 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/SettingsController.php:780 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/SettingsController.php:966 | greater.alwaysFalse | NOISE | Redundant comparison; behaviour correct. | - |
| app/Http/Controllers/Api/SitemapController.php:102 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/Api/SitemapController.php:104 | method.notFound | NOISE | Called on the Post relation query inside whereHas; Post::scopePublished exists. | - |
| app/Http/Controllers/Api/SitemapController.php:146 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/SpaPrerenderController.php:224 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/SpaPrerenderController.php:224 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/SpaPrerenderController.php:288 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/SpaPrerenderController.php:288 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/SpaPrerenderController.php:394 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Controllers/SpaPrerenderController.php:735 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Http/Middleware/ApiETag.php:67 | property.nonObject | NOISE | Middleware response typed class-string|object; runtime is Symfony Response. | - |
| app/Http/Middleware/ApiETag.php:70 | property.nonObject | NOISE | Middleware response typed class-string|object; runtime is Symfony Response. | - |
| app/Http/Middleware/ApiETag.php:72 | property.nonObject | NOISE | Middleware response typed class-string|object; runtime is Symfony Response. | - |
| app/Http/Middleware/ApiETag.php:77 | method.nonObject | NOISE | Middleware response typed class-string|object; runtime is Symfony Response. | - |
| app/Http/Middleware/ApiETag.php:78 | method.nonObject | NOISE | Middleware response typed class-string|object; runtime is Symfony Response. | - |
| app/Http/Middleware/ApiETag.php:80 | property.nonObject | NOISE | Middleware response typed class-string|object; runtime is Symfony Response. | - |
| app/Http/Resources/Cv/CvProjectResource.php:52 | method.notFound | NOISE | JsonResource proxies relationLoaded()/translations() to the wrapped model via __call. | - |
| app/Http/Resources/Cv/CvProjectResource.php:54 | method.notFound | NOISE | JsonResource proxies relationLoaded()/translations() to the wrapped model via __call. | - |
| app/Http/Resources/Cv/CvProjectResource.php:61 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/Cv/CvProjectResource.php:62 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/Cv/CvThoughtResource.php:42 | method.notFound | NOISE | JsonResource proxies relationLoaded()/translations() to the wrapped model via __call. | - |
| app/Http/Resources/Cv/CvThoughtResource.php:44 | method.notFound | NOISE | JsonResource proxies relationLoaded()/translations() to the wrapped model via __call. | - |
| app/Http/Resources/Cv/CvThoughtResource.php:52 | method.notFound | NOISE | JsonResource proxies relationLoaded()/translations() to the wrapped model via __call. | - |
| app/Http/Resources/PostResource.php:21 | method.notFound | NOISE | JsonResource proxies relationLoaded()/translations() to the wrapped model via __call. | - |
| app/Http/Resources/PostResource.php:21 | method.notFound | NOISE | JsonResource proxies relationLoaded()/translations() to the wrapped model via __call. | - |
| app/Http/Resources/PostResource.php:44 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/PostResource.php:45 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/PostResource.php:46 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/PostResource.php:51 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/PostResource.php:51 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/PostResource.php:52 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/PostResource.php:52 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/PostResource.php:99 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/ProjectResource.php:29 | method.notFound | NOISE | JsonResource proxies relationLoaded()/translations() to the wrapped model via __call. | - |
| app/Http/Resources/ProjectResource.php:30 | method.notFound | NOISE | JsonResource proxies relationLoaded()/translations() to the wrapped model via __call. | - |
| app/Http/Resources/ProjectResource.php:36 | method.notFound | NOISE | JsonResource proxies relationLoaded()/translations() to the wrapped model via __call. | - |
| app/Http/Resources/ProjectResource.php:74 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/ProjectResource.php:75 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Http/Resources/ProjectResource.php:76 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Jobs/CaptureInstagramPost.php:68 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/CaptureInstagramPost.php:68 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/CaptureInstagramPost.php:73 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/CaptureVideoCarousel.php:66 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/CaptureVideoCarousel.php:66 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/CaptureVideoCarousel.php:71 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/ExtractSlideContent.php:58 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/ExtractVideoSlides.php:57 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/ExtractVideoSlides.php:63 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateFacebookPost.php:87 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateFacebookPost.php:88 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateFacebookPost.php:92 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateInstagramPost.php:98 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateInstagramPost.php:99 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateInstagramPost.php:103 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateLinkedInPost.php:137 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateLinkedInPost.php:138 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateLinkedInPost.php:146 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateRebrandAssets.php:232 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateRebrandAssets.php:273 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateRebrandAssets.php:274 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateRebrandAssets.php:335 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Jobs/GenerateThreadsPost.php:74 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateThreadsPost.php:75 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateThreadsPost.php:79 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateTiktokPost.php:81 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateTiktokPost.php:82 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/GenerateTiktokPost.php:86 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/PublishViaZernio.php:108 | match.unhandled | NOISE | Platform is restricted to the 5 handled values by every dispatcher (PublisherResolver). | - |
| app/Jobs/RefetchSourceSlides.php:55 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/RefetchSourceSlides.php:55 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/RegenerateLinkedInCarouselContent.php:62 | argument.type | NOISE | File is not strict_types; int is coerced to string. | - |
| app/Jobs/ResearchRepurposeClaims.php:56 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Jobs/ReskinBookendSlide.php:85 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Jobs/RewriteRepurposeContent.php:55 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Models/LinkedInPost.php:230 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Models/Project.php:108 | identical.alwaysFalse | NOISE | Redundant comparison (count already non-zero). | - |
| app/Models/RepurposeJob.php:255 | method.notFound | NOISE | Relation returns LinkedInPost at runtime; method exists. | - |
| app/Models/RepurposeJob.php:255 | return.type | NOISE | Missing optional array key / Model vs LinkedInPost; every caller reads with ?? or on the right model. | - |
| app/Providers/AppServiceProvider.php:106 | nullCoalesce.expr | NOISE | Redundant ?? guard; harmless. | - |
| app/Providers/AppServiceProvider.php:106 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/AutoPipelineOrchestrator.php:225 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/AutoPipelineOrchestrator.php:242 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/AutoPipelineOrchestrator.php:258 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/AutoPipelineOrchestrator.php:319 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/AutoPipelineOrchestrator.php:507 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/CarouselCoverFigureEnricher.php:55 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Services/CarouselCoverFigureEnricher.php:120 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/CarouselCoverFigureEnricher.php:148 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/CarouselPersonPhotoEnricher.php:66 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Services/CarouselPersonStripRenderer.php:72 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/CarouselPersonStripRenderer.php:93 | arrayValues.list | NOISE | Static-analysis limitation; no runtime error. | - |
| app/Services/CarouselPersonStripRenderer.php:94 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/CarouselPersonStripRenderer.php:95 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/CarouselSlideEnhancer.php:196 | identical.alwaysTrue | NOISE | Redundant branch condition; no wrong logic. | - |
| app/Services/ContentPublishService.php:191 | parameterByRef.unusedType | NOISE | Static-analysis limitation; no runtime error. | - |
| app/Services/ContentPublishService.php:493 | nullCoalesce.variable | NOISE | Redundant ?? guard; harmless. | - |
| app/Services/ContentPublishService.php:550 | return.unusedType | NOISE | Static-analysis limitation; no runtime error. | - |
| app/Services/CoverBrandingEnhancer.php:283 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/CvMasterMarkdownService.php:60 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Services/CvMasterMarkdownService.php:174 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Services/CvMasterMarkdownService.php:174 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Services/CvMasterMarkdownService.php:187 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/CvMasterMarkdownService.php:187 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/CvMasterMarkdownService.php:188 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/CvMasterMarkdownService.php:188 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/CvMasterMarkdownService.php:195 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/CvMasterMarkdownService.php:254 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/CvMasterMarkdownService.php:254 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/CvMasterMarkdownService.php:255 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/CvMasterMarkdownService.php:255 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/CvMasterMarkdownService.php:278 | nullCoalesce.variable | NOISE | Redundant ?? guard; harmless. | - |
| app/Services/CvMasterMarkdownService.php:279 | nullCoalesce.variable | NOISE | Redundant ?? guard; harmless. | - |
| app/Services/EntityReferenceService.php:342 | booleanNot.alwaysTrue | NOISE | PHPStan treats the private check as pure and reuses an earlier result; a write happens between the two calls. | - |
| app/Services/EntityReferenceService.php:349 | deadCode.unreachable | NOISE | PHPStan treats the private check as pure and reuses an earlier result; a write happens between the two calls. | - |
| app/Services/FacebookGenerationService.php:81 | identical.alwaysTrue | NOISE | Redundant branch condition; no wrong logic. | - |
| app/Services/FacebookGenerationService.php:85 | deadCode.unreachable | NOISE | PHPStan treats the private check as pure and reuses an earlier result; a write happens between the two calls. | - |
| app/Services/FacebookGenerationService.php:310 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/FacebookGenerationService.php:320 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Services/GeminiGenCircuitBreaker.php:173 | smaller.alwaysTrue | NOISE | Redundant comparison; behaviour correct. | - |
| app/Services/GeminiGenVideoService.php:220 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Services/ImageGenerationService.php:415 | identical.alwaysFalse | NOISE | Larastan derives type enum from the first migration; later migration widened it to include carousel_slide. | - |
| app/Services/ImageGenerationService.php:1172 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/InstagramCaptureService.php:71 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/InstagramCaptureService.php:75 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/InstagramCaptureService.php:76 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/InstagramCaptureService.php:77 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/InstagramGenerationService.php:186 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/InstagramGenerationService.php:193 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/InstagramGenerationService.php:209 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/InstagramGenerationService.php:241 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Services/InstagramGenerationService.php:335 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/LinkedInCarouselImageService.php:62 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Services/LinkedInCarouselImageService.php:274 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/LinkedInCarouselImageService.php:1116 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/LinkedInGenerationService.php:1428 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/LinkedInPublishService.php:86 | method.notFound | NOISE | Account is a LinkedInAccount at runtime; method exists. | - |
| app/Services/LinkedInPublishService.php:88 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/LinkedInPublishService.php:111 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/LinkedInPublishService.php:112 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/LinkedInPublishService.php:159 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/LinkedInPublishService.php:340 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/LinkedInPublishService.php:580 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Services/LinkedInPublishService.php:660 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/LinkedInScheduleConflictService.php:47 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/PipelineGuard.php:25 | method.notFound | NOISE | Callers pass ContentIdea/LinkedInPost (both use HasStatusTransitions). | - |
| app/Services/PublerClient.php:168 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Services/PublerClient.php:273 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Services/PublerClient.php:807 | notIdentical.alwaysTrue | NOISE | Redundant comparison; behaviour correct. | - |
| app/Services/RepurposeCarouselBuilder.php:167 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/RepurposeCarouselBuilder.php:238 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/RepurposeHookTitleResolver.php:76 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/SourceFaceLocator.php:41 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Services/SourceFaceLocator.php:44 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/SourceFaceLocator.php:61 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/SourceFaceLocator.php:127 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Services/SourceFaceLocator.php:130 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/TelegramNotificationService.php:238 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/TelegramNotificationService.php:239 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/TelegramNotificationService.php:280 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/TelegramNotificationService.php:303 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/TelegramNotificationService.php:304 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/TelegramNotificationService.php:351 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/TelegramNotificationService.php:352 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/TelegramNotificationService.php:799 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/TelegramNotificationService.php:802 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/TelegramNotifier.php:71 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/TelegramNotifier.php:72 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/TelegramNotifier.php:73 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| app/Services/TelegramNotifier.php:114 | function.alreadyNarrowedType | NOISE | Defensive type check; harmless. | - |
| app/Services/ThreadsGenerationService.php:193 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/ThreadsGenerationService.php:204 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Services/ThreadsGenerationService.php:267 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/TiktokGenerationService.php:170 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/TiktokGenerationService.php:187 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/TiktokGenerationService.php:197 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| app/Services/TiktokGenerationService.php:272 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/TopicScoringService.php:203 | ternary.alwaysTrue | NOISE | Redundant ternary condition on non-empty value. | - |
| app/Services/TrendingTopicService.php:465 | ternary.alwaysTrue | NOISE | Redundant ternary condition on non-empty value. | - |
| app/Services/VideoBookendOverlayApplier.php:65 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/VideoHookSceneAuthor.php:53 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/VideoRebrandComposer.php:78 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/VideoRebrandComposer.php:82 | argument.type | NOISE | Eloquent relation/first() typed as Model; runtime object is the expected model. | - |
| app/Services/VideoSlideExtractor.php:50 | return.type | NOISE | Missing optional array key / Model vs LinkedInPost; every caller reads with ?? or on the right model. | - |
| app/Services/VideoSlideExtractor.php:53 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/VideoSlideExtractor.php:57 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| app/Services/ZernioPayloadBuilder.php:136 | method.notFound | NOISE | Relation returns LinkedInPost at runtime; method exists. | - |
| app/Services/ZernioPayloadBuilder.php:204 | method.notFound | NOISE | Relation returns LinkedInPost at runtime; method exists. | - |
| app/Support/ScheduledTaskSignatureExtractor.php:68 | identical.alwaysFalse | NOISE | Redundant comparison (count already non-zero). | - |
| app/Traits/HasSeoFields.php:5 | trait.unused | NOISE | Static-analysis limitation; no runtime error. | - |
| database/factories/GalleryFactory.php:61 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| database/factories/LinkedInPostFactory.php:29 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| database/migrations/2026_05_07_100001_create_instagram_posts_table.php:33 | method.notFound | NOISE | Fluent __call sets the attribute; comment() on an FK is accepted at runtime. | - |
| database/migrations/2026_05_07_100002_create_tiktok_posts_table.php:31 | method.notFound | NOISE | Fluent __call sets the attribute; comment() on an FK is accepted at runtime. | - |
| database/migrations/2026_05_08_100002_create_facebook_posts_table.php:38 | method.notFound | NOISE | Fluent __call sets the attribute; comment() on an FK is accepted at runtime. | - |
| database/migrations/2026_05_10_100001_create_threads_posts_table.php:26 | method.notFound | NOISE | Fluent __call sets the attribute; comment() on an FK is accepted at runtime. | - |
| database/migrations/2026_05_10_130001_create_short_links_table.php:33 | method.notFound | NOISE | Fluent __call sets the attribute; comment() on an FK is accepted at runtime. | - |
| database/migrations/2026_06_16_000001_create_reddit_posts_table.php:28 | method.notFound | NOISE | Fluent __call sets the attribute; comment() on an FK is accepted at runtime. | - |
| database/seeders/BlogPromoSettingsSeeder.php:24 | if.alwaysTrue | NOISE | Seeder guard on a firstOrCreate result; redundant. | - |
| database/seeders/CreatorBrandSettingsSeeder.php:32 | if.alwaysTrue | NOISE | Seeder guard on a firstOrCreate result; redundant. | - |
| database/seeders/CvSettingsSeeder.php:90 | if.alwaysTrue | NOISE | Seeder guard on a firstOrCreate result; redundant. | - |
| database/seeders/LinkedInPostSeeder.php:63 | larastan.relationExistence | NOISE | Relation exists as an untyped method on the model; Larastan needs a return type to see it. | - |
| database/seeders/LinkedInPostSeeder.php:75 | nullsafe.neverNull | NOISE | Nullsafe on an Eloquent relation/attribute PHPStan believes non-null; DB can hold null, guard is correct. | - |
| database/seeders/LinkedInSettingsSeeder.php:138 | if.alwaysTrue | NOISE | Seeder guard on a firstOrCreate result; redundant. | - |
| database/seeders/MailSettingsSeeder.php:37 | if.alwaysTrue | NOISE | Seeder guard on a firstOrCreate result; redundant. | - |
| database/seeders/PublerSettingsSeeder.php:53 | if.alwaysTrue | NOISE | Seeder guard on a firstOrCreate result; redundant. | - |
| database/seeders/ScheduledCommandSeeder.php:337 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| database/seeders/ScheduledCommandSeeder.php:339 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| database/seeders/TelegramSettingsSeeder.php:79 | if.alwaysTrue | NOISE | Seeder guard on a firstOrCreate result; redundant. | - |
| database/seeders/ZernioSettingsSeeder.php:70 | if.alwaysTrue | NOISE | Seeder guard on a firstOrCreate result; redundant. | - |
| routes/api.php:595 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| routes/api.php:599 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
| routes/api.php:603 | nullCoalesce.offset | NOISE | Defensive ?? on a result array whose shape is documented; harmless. | - |
