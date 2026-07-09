# Changelog

## [1.7.0](https://github.com/middag-io/middag-php-moodle/compare/v1.6.0...v1.7.0) (2026-07-09)


### ⚠ BREAKING CHANGES

* **moodle:** enum FQCNs moved and @api method names changed; product (local_middag, §P/LB-PLG-00) must rewire.

### Refactoring

* **moodle:** relocate Domain enums to per-concern Enum/ and camelCase method names (D-047) ([274332f](https://github.com/middag-io/middag-php-moodle/commit/274332fb2087166e20fdf1128cd0ea6f25c0e78d))


### Miscellaneous

* **deps:** raise framework floor to ^1.7 ([c53bdd9](https://github.com/middag-io/middag-php-moodle/commit/c53bdd9312b4614c907a339567832bd6c4bd42ed))

## [1.6.0](https://github.com/middag-io/middag-php-moodle/compare/v1.5.0...v1.6.0) (2026-07-09)


### ⚠ BREAKING CHANGES

* **runtime:** Middag\Moodle\Kernel\* FQCNs are gone; import Middag\Moodle\Runtime\* instead. Framework contracts (Middag\Framework\Kernel\*) are unaffected.

### Refactoring

* **runtime:** move Kernel namespace to Runtime ([fd5f6da](https://github.com/middag-io/middag-php-moodle/commit/fd5f6dafc6a5d7a42aa3f706be37c0b75ab29c42))


### Miscellaneous

* release moodle 1.6.0 ([957ec8f](https://github.com/middag-io/middag-php-moodle/commit/957ec8f54f668ac016c1888e9e566585c44e3219))

## [1.5.0](https://github.com/middag-io/middag-php-moodle/compare/v1.4.0...v1.5.0) (2026-07-09)


### ⚠ BREAKING CHANGES

* enum case names changed for SettingType, PermissionType, CapabilityRisk, CapabilityType, TextFormat, the 17 Domain/* enums and usages of the renamed middag-io/framework and middag-io/ui cases. Consumers referencing cases by name must update (values unchanged).
* the 22 settings DSL classes moved from Middag\Moodle\Settings\* to Middag\Moodle\Settings\Type\*. Consumers must update imports.
* **settings:** the @api base class Middag\Moodle\Settings\Setting was renamed to AbstractSetting (maintainer rename, folded into this commit); consumers subclassing or type-hinting Setting must update the import.
* drop pre-4.5 compat and stale product-era paths
* **settings:** move SettingType to Settings\Enum namespace

### Features

* move the 22 settings DSL classes to Settings/Type/ ([685e533](https://github.com/middag-io/middag-php-moodle/commit/685e5331ea3cb847fdcd6e55619b56f99e779e0a))
* rename all enum cases to strict PascalCase (PER-CS 2.0) ([916cd44](https://github.com/middag-io/middag-php-moodle/commit/916cd44fed4c7ea6325505f2909aa82fa6c5711b))
* **settings:** injectable naming policy + rename Setting to AbstractSetting ([3707962](https://github.com/middag-io/middag-php-moodle/commit/37079621afa4d0c3821937f9de4373e67f6d01f9))


### Refactoring

* drop pre-4.5 compat and stale product-era paths ([3c5af37](https://github.com/middag-io/middag-php-moodle/commit/3c5af37998722f2c4598e86a196602a8a30a1193))
* **settings:** move SettingType to Settings\Enum namespace ([7976153](https://github.com/middag-io/middag-php-moodle/commit/79761532fbf31efa79f2865e043380d5a76ccecf))


### Documentation

* **facade:** prove and document the bootless third-party facade seam ([4c805df](https://github.com/middag-io/middag-php-moodle/commit/4c805df04815698591e6eee97845f8fac130bae0))
* retire blocked-work backlog ([86b7348](https://github.com/middag-io/middag-php-moodle/commit/86b7348037cefed16d5ba78c6d1046867030268c))


### Miscellaneous

* **deps:** raise framework floor to ^1.5 ([8ee8003](https://github.com/middag-io/middag-php-moodle/commit/8ee800308ea2cd8193f71a16d70f592fff101f4c))
* **deps:** raise framework floor to ^1.6, ui to ^1.3 ([69624e5](https://github.com/middag-io/middag-php-moodle/commit/69624e5a73a975f5d23a7e323b4a593e61f9d001))
* **deps:** raise framework floor to ^1.6, ui to ^1.3 ([97bd8c2](https://github.com/middag-io/middag-php-moodle/commit/97bd8c2de8c3b8de0382772235f5de0bdcf0366e))
* release moodle 1.5.0 ([0dc0d8c](https://github.com/middag-io/middag-php-moodle/commit/0dc0d8c6d5dba36721a5532833a9fda6ae40d430))
* release moodle 1.5.0 ([a6c0502](https://github.com/middag-io/middag-php-moodle/commit/a6c0502449034c9e7a78f7ff0696fe6146f8a8f6))

## [1.4.0](https://github.com/middag-io/middag-php-moodle/compare/v1.3.0...v1.4.0) (2026-07-08)


### ⚠ BREAKING CHANGES

* normalize Moodle adapter structure

### Features

* **http:** honor per-requirement capability context from rich #[Auth] [LB-2-04] ([8eccb30](https://github.com/middag-io/middag-php-moodle/commit/8eccb30c9d5670ff2f559038d67922dbc2ad9aba))


### Bug Fixes

* **http:** resolve #[Auth] string context instead of degrading to SYSTEM [N-03/LB-2-04] ([718a8a0](https://github.com/middag-io/middag-php-moodle/commit/718a8a0f7f9251f78f4feef267bfa2a2a5421d28))


### Refactoring

* normalize Moodle adapter structure ([438a606](https://github.com/middag-io/middag-php-moodle/commit/438a606071d5c889e39307a6199ed46d855aaa5d))


### Miscellaneous

* **deps:** require middag-io/framework ^1.3.0 ([707e0dd](https://github.com/middag-io/middag-php-moodle/commit/707e0dda7f6d322bbf04fba2e090d74d03d075d9))
* release moodle 1.4.0 ([181cd95](https://github.com/middag-io/middag-php-moodle/commit/181cd9597b07ca387b9378c8b7aa7e6069f9be04))

## [1.3.0](https://github.com/middag-io/middag-php-moodle/compare/v1.2.3...v1.3.0) (2026-07-07)


### ⚠ BREAKING CHANGES

* **security:** `Middag\Moodle\Security\AuthService`, `Middag\Moodle\Security\Contract\AuthServiceInterface` and `Middag\Moodle\Settings\framework_config` are removed, and `AbstractApiController` no longer gates requests on an `api_enabled` setting. Consumers that relied on the adapter's JWT auto-login / support-login must migrate to the product auth (core + licensing SSO) and enforce any "API enabled" policy in their own controllers.
* **config:** Capability::middag() and Capability::is_middag() are removed. Use Capability::forHostComponent() and Capability::isHostComponent() instead; both derive the capability component from the configured ComponentContext, so the running host plugin must call ComponentContext::configure() during bootstrap (already a hard requirement of the adapter).

### Bug Fixes

* correct two bugs surfaced by coverage (AuthService default, MessagePermission bits) ([87fcabd](https://github.com/middag-io/middag-php-moodle/commit/87fcabdf4c5297f7d38d17921e2176620374c33b))
* **http:** repair vestigial concern traits and two active controller bugs ([8b5bcc0](https://github.com/middag-io/middag-php-moodle/commit/8b5bcc0f10bbc156a67fa786b6bd496e489a6c88))
* **support:** correct three Moodle API delegation bugs surfaced by coverage ([8a5d787](https://github.com/middag-io/middag-php-moodle/commit/8a5d787b0b29f9304ee1c7db53b6b3aa946936b9))
* **support:** repair Moodle 5.0 check action-link and completion reads ([457ca27](https://github.com/middag-io/middag-php-moodle/commit/457ca27cd29dd75f48a310509b9f5e9ea196ed1a))


### Refactoring

* **config:** derive host component paths and capabilities from ComponentContext ([b48bdcb](https://github.com/middag-io/middag-php-moodle/commit/b48bdcb2cfa92a7b602d8ef6b056f5c06fc9fb51))
* **kernel:** isolate the CLI fatal-boot path in a dedicated method ([1e4dbbe](https://github.com/middag-io/middag-php-moodle/commit/1e4dbbecb4d8d2eb1802d01b2d1019daa2b31d05))
* **persistence:** compile SQL conditions with an exhaustive match ([fca1013](https://github.com/middag-io/middag-php-moodle/commit/fca10133a63839a2b63af1e3a0ba869678c68e81))
* **security:** drop product auth and framework-config from the OSS adapter ([27db9c2](https://github.com/middag-io/middag-php-moodle/commit/27db9c281a444c6dbf23c33276f4e2c3f6e582fc))
* use PascalCase internal imports across the adapter ([487d6c4](https://github.com/middag-io/middag-php-moodle/commit/487d6c4689a5564a0ca4af046d813203280fd461))


### Documentation

* **backlog:** catalog residual unreachable guards after 98.62% coverage ([bf25282](https://github.com/middag-io/middag-php-moodle/commit/bf252824061b87a19d88be6cb64dba4aee72f3f6))
* **backlog:** record QG-MDL-03 unreachable guard lines as blocked ([4fde2c6](https://github.com/middag-io/middag-php-moodle/commit/4fde2c67e51e1fb76667e22c1eaaec143e35ad46))
* **readme:** drop removed PDF and outbox from the adapter surface ([a537e02](https://github.com/middag-io/middag-php-moodle/commit/a537e02ccde26ff8850f198d06567281f1f853aa))


### Miscellaneous

* **ci:** refresh stale registry and reusable-workflow comments ([f1e7f5f](https://github.com/middag-io/middag-php-moodle/commit/f1e7f5f6e386d60077c1083ca48aa598b91c68a4))
* **coverage:** exclude genuinely uncoverable artifacts from reports ([6b86e43](https://github.com/middag-io/middag-php-moodle/commit/6b86e4339d85eb2e0c90644fea8c9a0a606536a8))
* **dist:** export-ignore /bin from the distribution tarball ([d5756a0](https://github.com/middag-io/middag-php-moodle/commit/d5756a0ba0d04c7dbbcdd413506cd91d8b292696))
* promote develop to main (release 1.3.0) ([14bba1e](https://github.com/middag-io/middag-php-moodle/commit/14bba1e65cbbab33cbbd429b7fee93a30d0bb212))
* release 1.3.0 ([61a38db](https://github.com/middag-io/middag-php-moodle/commit/61a38dba26aa12eb40b993eb925b66039302ad67))

## [1.2.3](https://github.com/middag-io/middag-php-moodle/compare/v1.2.2...v1.2.3) (2026-07-05)


### Bug Fixes

* **filesystem:** reject ".." traversal in MoodledataFilesystem subdirectory ([207cbb2](https://github.com/middag-io/middag-php-moodle/commit/207cbb20b4aed10fdca3f0f887917e0fd6330250))
* **filesystem:** reject null byte in MoodledataFilesystem subdirectory ([8c1e99f](https://github.com/middag-io/middag-php-moodle/commit/8c1e99f8b5f6f2ed9015072d7ee174d4f42acedc))


### Documentation

* **api:** add API-STABILITY.md and link it from CONTRIBUTING ([4975ab7](https://github.com/middag-io/middag-php-moodle/commit/4975ab7325feb8b746eeab0899bcd0203f742bb1))
* **api:** tag remaining src types @api/[@internal](https://github.com/internal) ([c63738d](https://github.com/middag-io/middag-php-moodle/commit/c63738dc93627c5bf6a7278728d8e1adfa39317a))


### Miscellaneous

* **composer:** add keywords, homepage and support metadata ([ccb6e7a](https://github.com/middag-io/middag-php-moodle/commit/ccb6e7a189b61d7afac5fdc07f924749a7a2f35d))
* **deps:** declare reciprocal conflict on core &lt;1.2 (BUG7) ([4f1574a](https://github.com/middag-io/middag-php-moodle/commit/4f1574a43c2b6401c4088f2870db8b5c3db37706))
* **hooks:** accept the breaking-change marker in commit-msg ([21cc17a](https://github.com/middag-io/middag-php-moodle/commit/21cc17a24812e89a3adee28b11180f15255711ca))
* **phpstan:** drop dangling empty excludePaths key ([bf51166](https://github.com/middag-io/middag-php-moodle/commit/bf5116668c38a791f2cc0fd38fce8eb5b1149bb7))

## [1.2.2](https://github.com/middag-io/middag-php-moodle/compare/v1.2.1...v1.2.2) (2026-07-04)


### Bug Fixes

* **deps:** require firebase/php-jwt ^7.0 again ([00fa6ec](https://github.com/middag-io/middag-php-moodle/commit/00fa6ec734ff159d2ade9adbafba79b63bab89f3))
* **deps:** require firebase/php-jwt ^7.0 again ([f67747a](https://github.com/middag-io/middag-php-moodle/commit/f67747aea75fa0f5e24e33bc67f12e6a72e79d50))

## [1.2.1](https://github.com/middag-io/middag-php-moodle/compare/v1.2.0...v1.2.1) (2026-07-04)


### Bug Fixes

* **kernel:** run every reset callback before rethrowing the first failure ([29111e4](https://github.com/middag-io/middag-php-moodle/commit/29111e4f2d7318a957172abcc85443369c4fd726))

## [1.2.0](https://github.com/middag-io/middag-php-moodle/compare/v1.1.1...v1.2.0) (2026-07-04)


### Features

* **kernel:** chain product reset callbacks into the ContainerFactory boot seam ([d0d3568](https://github.com/middag-io/middag-php-moodle/commit/d0d3568782edd06bcba65dd35afa2d4d05fa187c))


### Bug Fixes

* **settings:** normalise PascalCase config enums and reject underivable slugs ([f131c0e](https://github.com/middag-io/middag-php-moodle/commit/f131c0e29d7776b4a67e2ec7e21237c89061aadf))


### Miscellaneous

* **deps:** allow firebase/php-jwt ^6.11 alongside ^7.0 ([77e20c3](https://github.com/middag-io/middag-php-moodle/commit/77e20c3a145518638bfd2d636eda603d0c80e3e6))

## [1.1.1](https://github.com/middag-io/middag-php-moodle/compare/v1.1.0...v1.1.1) (2026-07-03)


### ⚠ BREAKING CHANGES

* **pdf:** Middag\Moodle\Pdf\PdftkAdapter and the mikehaertl/php-pdftk dependency were removed. Consumers needing PDFTk must use the proprietary core package integration instead.
* **translation:** the local Middag\Moodle\Translation\TranslatorInterface port was removed. Consumers must depend on Middag\Framework\Translation\Contract\TranslatorInterface, and MoodleTranslator::trans(...) was replaced by get(string $key, string $component = '', array $params = []).
* **structure:** public class names and namespaces renamed; consumers must update imports (Middag\Moodle\Translation\Translator -> MoodleTranslator, Middag\Moodle\Kernel\MoodleHttpKernel -> Middag\Moodle\Http\MoodleHttpKernel, Definition short names now suffixed with Definition, Security\Service\AuthService -> Security\AuthService).

### Features

* **pdf:** remove the pdftk adapter from the oss surface ([d3260c8](https://github.com/middag-io/middag-php-moodle/commit/d3260c85885e0a03d793f713d3cc0a6de498f093))
* **translation:** wire the framework translator port ([e6a158c](https://github.com/middag-io/middag-php-moodle/commit/e6a158c529cd2ce0a107c1cfc95a0a5743c26156))


### Bug Fixes

* **kernel:** resolve host paths through the component registry ([414c2d7](https://github.com/middag-io/middag-php-moodle/commit/414c2d7c4b4c5381f12d856e256b8a57a1492f6a))


### Refactoring

* **structure:** apply oss-audit batch a renames ([1a711c4](https://github.com/middag-io/middag-php-moodle/commit/1a711c49fc3dcfba0f8466679a84a84be8156cbb))


### Documentation

* align versioning text with family 1.x policy and major control ([69b59e9](https://github.com/middag-io/middag-php-moodle/commit/69b59e9c5b4dcc048e9ca7f394fdb10afeef20fc))
* **claude:** rewrite agent guide in English for the post-audit layout ([4599ebe](https://github.com/middag-io/middag-php-moodle/commit/4599ebec99bd72393060a098fe15afe8e59f27b8))
* **contributing:** record the audit-consolidation patch exception ([46c07f6](https://github.com/middag-io/middag-php-moodle/commit/46c07f61fb2c502d8b3c4a4c62e8153123a75710))
* point boundary enforcement at the isolation guard test ([1285fc6](https://github.com/middag-io/middag-php-moodle/commit/1285fc6c7875bcfe37262e33d95a22bd5799f242))
* sibling framework path repo lives in the consumer root ([333f924](https://github.com/middag-io/middag-php-moodle/commit/333f92483ee0596b5888daa2f91333b02adce619))
* translate remaining Portuguese docblocks and comments to English ([e974e71](https://github.com/middag-io/middag-php-moodle/commit/e974e71b2ca1dc8b24355d3f017a5f6b8ed801dd))


### Miscellaneous

* **composer:** align runtime deps with actual src usage ([4ba3037](https://github.com/middag-io/middag-php-moodle/commit/4ba3037b2c8065e38eef8315ca2358fad123fd56))
* **composer:** align scripts with the canonical baseline ([5ef71b5](https://github.com/middag-io/middag-php-moodle/commit/5ef71b517571a379ae4462e2453e87397d5275bf))
* **dev:** add php 8.2 parse-level lint script ([aa9cb69](https://github.com/middag-io/middag-php-moodle/commit/aa9cb693a17c5b1f271ec4dd58422557e2339d5d))
* drop inert pre-major bump flags from release-please config ([f5177f5](https://github.com/middag-io/middag-php-moodle/commit/f5177f52482525a170d19201fac56776f7f9e41b))
* release 1.1.1 ([5fdb2e1](https://github.com/middag-io/middag-php-moodle/commit/5fdb2e19c3ea6f13785f1e04bf9751008213e447))

## [1.1.0](https://github.com/middag-io/middag-php-moodle/compare/v1.0.2...v1.1.0) (2026-07-03)


### ⚠ BREAKING CHANGES

* **structure:** most class namespaces moved; Enum\Visibility is now Domain\Course\CourseVisibility. Consumers must update imports.

### Features

* **filesystem:** add MoodledataFilesystem wiring the framework Filesystem port over dataroot ([f92e155](https://github.com/middag-io/middag-php-moodle/commit/f92e15588090c16c208f9d2f73e11a4d9b8a8b4e))
* **mail:** add MoodleMailer wiring the framework Mail port over email_to_user ([f476eab](https://github.com/middag-io/middag-php-moodle/commit/f476eabe57e5ac0373d9fd27fe7fa2bfac327d55))


### Bug Fixes

* **support:** cast ob_get_clean() before emptiness check ([c4ce752](https://github.com/middag-io/middag-php-moodle/commit/c4ce7527492780aeca86c4d9ac6de3d75bbdfde4))


### Refactoring

* **structure:** reorganize namespaces to host adapter layout ([5eaba06](https://github.com/middag-io/middag-php-moodle/commit/5eaba06d3cb2261096f4fd334b838ddbd6c68f2a))


### Miscellaneous

* **deps:** require middag-io/ui ^1.2 and middag-io/framework ^1.0.2 ([027007f](https://github.com/middag-io/middag-php-moodle/commit/027007f85e9a1d20a111c93abd84986c6db829a2))

## [1.0.2](https://github.com/middag-io/middag-php-moodle/compare/v1.0.1...v1.0.2) (2026-06-30)


### ci

* inline release-please workflow + pin next release to 1.0.2 ([72d25e2](https://github.com/middag-io/middag-php-moodle/commit/72d25e2ff8add2df5c40a079a3c6d5f978166eb2))


### Features

* **inertia:** Inertia v3 bootstrap, inertia_app entry, component-derived web base ([831f1e9](https://github.com/middag-io/middag-php-moodle/commit/831f1e9343c570a8a6161c75b92f633730f8a698))


### Bug Fixes

* **inertia:** use modern core\component class in componentWebBase ([3b4b270](https://github.com/middag-io/middag-php-moodle/commit/3b4b270d22c74b0302b0db8484710e1e21b0c84a))

## [1.0.1](https://github.com/middag-io/middag-php-moodle/compare/v1.0.0...v1.0.1) (2026-06-27)


### Features

* default Inertia to React module and promote Environment to [@api](https://github.com/api) ([3e1827e](https://github.com/middag-io/middag-php-moodle/commit/3e1827e7b72863c0fbcf85460c9d31a3f2e7b3fb))

## [1.0.0](https://github.com/middag-io/middag-php-moodle/compare/v0.5.0...v1.0.0) (2026-06-27)


### Bug Fixes

* **deps:** require middag-io/framework ^1.0 ([1830ab6](https://github.com/middag-io/middag-php-moodle/commit/1830ab6b54a3e4bbeeebc642f5242727335683a9))


### Miscellaneous Chores

* release 1.0.0 ([a993420](https://github.com/middag-io/middag-php-moodle/commit/a99342012a89264cb68e1650191e9c30fae3d6bf))

## [0.5.0](https://github.com/middag-io/middag-php-moodle/compare/v0.4.0...v0.5.0) (2026-06-26)


### Features

* **definition:** add capabilities to Service external-function definition ([489b8ab](https://github.com/middag-io/middag-php-moodle/commit/489b8ab1b32e910341f3c455706b1d295e67a048))

## [0.4.0](https://github.com/middag-io/middag-php-moodle/compare/v0.3.0...v0.4.0) (2026-06-26)


### Miscellaneous Chores

* initial public release ([c99f8b7](https://github.com/middag-io/middag-php-moodle/commit/c99f8b735618a0f23ecb70951211daea1c77fc72))

## [0.3.0](https://github.com/middag-io/middag-php-moodle/compare/v0.2.2...v0.3.0) (2026-06-06)


### Features

* **bus:** add send-only Messenger transport over Moodle adhoc queue ([779c445](https://github.com/middag-io/middag-php-moodle/commit/779c4457655aa00e1facbf53e43419a17a14ca3c))


### Bug Fixes

* **extensions:** resolve consumer extension discovery after kernel extraction ([baca071](https://github.com/middag-io/middag-php-moodle/commit/baca071c00fde806f2dcbbd5fd297c2d0f90c119))
* **http:** emit PSR-7 response from kernel + wrap controller urlGenerator ([7fa8d03](https://github.com/middag-io/middag-php-moodle/commit/7fa8d0312c63506d135c64257ce86bbc655258f7))
* **kernel:** build PSR-7 request from globals in Kernel::handle() ([4a3079f](https://github.com/middag-io/middag-php-moodle/commit/4a3079f3adb8b4692a7f0941808660afa1b40c40))
* **moodle-adapter:** OSS unblock - framework v0.11.0/ui v1.0 reconcile + local host contracts ([ff305b1](https://github.com/middag-io/middag-php-moodle/commit/ff305b14c327236f056ab4e520a13a4a46657105))
* **moodle-adapter:** reconcile framework v0.11 contract drift ([0d40734](https://github.com/middag-io/middag-php-moodle/commit/0d407342993a78fb014115791f29745b67d4bbe2))
* **routing:** return URL string from Router::generateUrl per RouterInterface ([f286283](https://github.com/middag-io/middag-php-moodle/commit/f286283f36d71403480e54e602e512e9d1555478))

## [0.2.2](https://github.com/middag-io/middag-php-moodle/compare/v0.2.1...v0.2.2) (2026-05-27)


### Bug Fixes

* **kernel:** scan default item/persistence layer + camelCase DI wiring ([ea0ca00](https://github.com/middag-io/middag-php-moodle/commit/ea0ca005f20edd91a006b97fa16ce45d0e3a3198))
* **kernel:** scan default item/persistence layer + camelCase DI wiring ([6c34151](https://github.com/middag-io/middag-php-moodle/commit/6c341510bcb529631005ff8af98b35e28d0f4b5c))

## [0.2.1](https://github.com/middag-io/middag-php-moodle/compare/v0.2.0...v0.2.1) (2026-05-27)


### Bug Fixes

* **kernel:** complete camelCase migration of facade/env/autoload call sites ([4065f2e](https://github.com/middag-io/middag-php-moodle/commit/4065f2e82e04085476f4e3597bcc429b0120641d))

## [0.2.0](https://github.com/middag-io/middag-php-moodle/compare/v0.1.1...v0.2.0) (2026-05-25)


### Features

* **admin:** SettingsTreeBuilder — encapsulate settings.php admin tree [B-069] ([1378d09](https://github.com/middag-io/middag-php-moodle/commit/1378d09ef9dcaacc49f77b1757334b3561ab81d3))
* **base:** add 2 missing Moodle-side abstract parents for plugin Base shims (B-044) ([1a37d52](https://github.com/middag-io/middag-php-moodle/commit/1a37d529ffa05c11216e600aef968698025e0804))
* **contract:** host ComplianceInterface (CC-08, B-005) ([97567b0](https://github.com/middag-io/middag-php-moodle/commit/97567b01c724e0ab51cc2bd1d04f257413af32eb))
* **contract:** host Conditions trio (Provider/Rule/Conditions) (B-006, PD-002 A) ([55015dd](https://github.com/middag-io/middag-php-moodle/commit/55015dd3ebc501c3dfdbebb7697449b2db455fb3))
* **contract:** MoodleControllerInterface + MoodleMiddagInterface bridges (B-007) ([a2b7b44](https://github.com/middag-io/middag-php-moodle/commit/a2b7b441e7b3784a1c611918e9f73460ee2ac316))
* **enum:** A2.F PermissionType lands in moodle adapter (D-012) — Middag\Moodle\Enum\PermissionType ([5dee382](https://github.com/middag-io/middag-php-moodle/commit/5dee382994d69cbe6c139d77a6a33dc7d69032d8))
* **extension:** bridge LicensedExtensionTrait and add MoodleLicenseCheckTask (B-025/B-026) ([080f96c](https://github.com/middag-io/middag-php-moodle/commit/080f96c433917e0905a5c322d0e4cc8a7f40a49f))
* **hook:** add AbstractExtendExtensions abstract parent (B-062) ([76d7412](https://github.com/middag-io/middag-php-moodle/commit/76d7412cb7367346f32a988e603eec167aac4b91))
* implement framework adapter contracts ([16f0ec5](https://github.com/middag-io/middag-php-moodle/commit/16f0ec518fddce3f59de2c2aebd00ea0737d20dc))
* **kernel:** A2.0.7 MoodleBootstrap — stub getProjectRoot/getOptions (D-024) ([895d6ce](https://github.com/middag-io/middag-php-moodle/commit/895d6cefb51e08ae3f5b044503eb7e01c27e060e))
* **licensing:** container wire for ADR-012 protected delivery ([20b7cba](https://github.com/middag-io/middag-php-moodle/commit/20b7cba6b1d42877b52afa97d768d876a41aeac8))
* **licensing:** Moodle adapter — manifest storage + refresh scheduled task ([f6da234](https://github.com/middag-io/middag-php-moodle/commit/f6da234dc4a332c0236756cf39c7259fe9f0ac2c))
* **logging:** A3.4.3 LogReaderService lands in moodle (D-040) ([6f03d37](https://github.com/middag-io/middag-php-moodle/commit/6f03d377d3a22941b66036b34a9d583c77886513))
* **logging:** wire framework LoggerFactory + drop FileLogger (PD-038) ([78c8a7d](https://github.com/middag-io/middag-php-moodle/commit/78c8a7dc81c8380aff05cdd6c486c93f42776408))
* **moodle:** AbstractHookCallbacks + AbstractMoodleObserver + Privacy Provider — B-070/B-071/B-063 ([2d365da](https://github.com/middag-io/middag-php-moodle/commit/2d365da1e5f3e5708a7e40ce7d5a129467f4775e))
* **moodle:** AbstractLicensedExtension for pro/custom distributions ([96eef6d](https://github.com/middag-io/middag-php-moodle/commit/96eef6de66fd4bcde81f12c81c704ee37941f563))
* **moodle:** add Kernel/Moodle/Widget/ (moved from framework) ([d5eb4fc](https://github.com/middag-io/middag-php-moodle/commit/d5eb4fca5f8a3daf4224e75861856d3ffb9d7c2c))
* **moodle:** C-aux2 ClockInterface binding in ContainerFactory (D-047) ([727cef7](https://github.com/middag-io/middag-php-moodle/commit/727cef7f371b315ddf8169736123feb30f787beb))
* **moodle:** MoodleHookfileLoader with 4 discovery sources ([b4bc9c1](https://github.com/middag-io/middag-php-moodle/commit/b4bc9c1b9262bda6005fe2c38b6761fccfe36e32))
* **moodle:** RemoteSupport + AntiTamper adapters per ADR-013/014 ([fd20689](https://github.com/middag-io/middag-php-moodle/commit/fd20689b4019d38b1305df0e63192e20003ff182))
* **moodle:** Statics generators ported from plugin build_statics_lib.php ([4b6da30](https://github.com/middag-io/middag-php-moodle/commit/4b6da30bca3b5ca5bb7a031d2571b2740e199876))
* **moodle:** wire RemoteSupport + AntiTamper licensing stacks (ADR-013/014) ([747cb3f](https://github.com/middag-io/middag-php-moodle/commit/747cb3fcab63fe3f0642963ede684f825720f2f6))
* **output:** add Renderer base and UsersTable + UsersFilterset (B-060) ([dadfe0a](https://github.com/middag-io/middag-php-moodle/commit/dadfe0af1b43f6120a6baa54a06367836ae53b61))
* **persistence:** MigrationOrchestrator + xmldb adapter + helpers [B-065] ([ab18f9a](https://github.com/middag-io/middag-php-moodle/commit/ab18f9ab35ec51e5e670b4e903c84bdf2d7b2587))
* **route:** stub MiddagProxy placeholder for Moodle 5.1+ Symfony bridge (B-015, ADR-208) ([d234223](https://github.com/middag-io/middag-php-moodle/commit/d2342231119a1b5da62cf41b513d36fd2eb09822))
* **support:** add Moodle static aggregator for all 41 supports (B-048) ([4d78ed7](https://github.com/middag-io/middag-php-moodle/commit/4d78ed7e66f1890a903607f01a3deb48172f0f46))
* **support:** host TaskDefinitionBuilder for Schedule → db/tasks.php (B-008) ([f3034f3](https://github.com/middag-io/middag-php-moodle/commit/f3034f39f87f886b0861a17fd092606d6c9dccfb))
* **task:** port Async/Outbox tasks to Middag\Moodle\Task [B-061] ([22ea09b](https://github.com/middag-io/middag-php-moodle/commit/22ea09bb3a7bca7f4b0d0b9689daf616c7cf9ee8))
* **transport:** add MoodleAdhocTaskTransport for JobBus (B-152) ([1e05ec4](https://github.com/middag-io/middag-php-moodle/commit/1e05ec4ce7e24707305ecd5c3b1c6a5868ce1675))
* **webservice:** AbstractExternal base class for plugin nominal external classes [B-075] ([16b4b89](https://github.com/middag-io/middag-php-moodle/commit/16b4b89108960d456b2418cb3f36875706276abd))


### Bug Fixes

* **check:** close all PHPStan errors — pdftk dep, Pdf interface, HttpKernel wiring ([4025b2f](https://github.com/middag-io/middag-php-moodle/commit/4025b2f96c812ad5de4de0ab9d4384271da83740))
* **di:** close BL-P0-MOODLE-DEAD-DI — rename 11 snake_case promoted props to camelCase ([04670c0](https://github.com/middag-io/middag-php-moodle/commit/04670c092185ea4066685c5a54e3b43c3d9d10a9))
* **di:** extend BL-P0-MOODLE-DEAD-DI sweep to 16 Rector-flagged files ([54013f0](https://github.com/middag-io/middag-php-moodle/commit/54013f093ddc3fe99c1e4cc12edeb8b1d09bdd58))
* **imports:** A2.B update imports to Middag\Core\* (ActivityTrackable, ImportRepository, TypeLoaderInterface) ([307cbf1](https://github.com/middag-io/middag-php-moodle/commit/307cbf1cc75c4c463ee72e63fa2e74367b77b0bf))
* **imports:** A2.C moodle — BootFailurePolicy: use core class where instantiated, LoaderFailurePolicyInterface where typed ([fccd0d9](https://github.com/middag-io/middag-php-moodle/commit/fccd0d9dbc014dd839ee6c190cc41946ba9d09ce))
* **imports:** A2.C update imports to Middag\Core\* (ExtensionGroup, ExtensionDistribution, ExtensionServiceInterface) ([a9bbbf9](https://github.com/middag-io/middag-php-moodle/commit/a9bbbf980973f5def6ad5071112bc0842b7b0121))
* **imports:** A2.E update imports to Middag\Core\* (MiddagInterface, Shortcodes, NavigationRegistry) ([0524a93](https://github.com/middag-io/middag-php-moodle/commit/0524a93e030b07953ba47310ae3f54d7eafaef1e))
* **imports:** A2.G moodle — QueryBuilder → MiddagItemQueryBuilder, PersistenceScope → Core\Shared\ValueObject ([5a89c6e](https://github.com/middag-io/middag-php-moodle/commit/5a89c6e4dc2e20ff1139ebe7c49e501377e911fa))
* **imports:** A3.7.2 update Bus contracts to Contract\Bus\* (framework D-045) ([673b5cb](https://github.com/middag-io/middag-php-moodle/commit/673b5cb7724eb4ea2d9f24a4ec57ba0fc0125937))
* **imports:** A3.7.3 update HTTP contracts to Contract\Http\* (framework D-045) ([b2ad781](https://github.com/middag-io/middag-php-moodle/commit/b2ad7810ac87c06df32d980a0be241412dd0f8f3))
* **imports:** A3.7.5 update Entity/Form contracts to Contract\Entity\* / Contract\Form\* (framework D-045) ([3f08fd0](https://github.com/middag-io/middag-php-moodle/commit/3f08fd0711dacb7371a0b0f3516062d599736db6))
* **imports:** A3.7.6 residual — FrameworkConfig namespace update (D-045) ([8a456a0](https://github.com/middag-io/middag-php-moodle/commit/8a456a09789a98113fc29b3f1a30c3ca7effa7df))
* **imports:** A3.7.6 update Core cross-cutting contracts to Contract\Core\* (framework D-045) ([e8112b5](https://github.com/middag-io/middag-php-moodle/commit/e8112b5405384fe33a2feb4695b332954ffd9876))
* **kernel:** A2.0.8 update LicenseInterface import → Middag\Licensing\Contract (D-021) ([127939e](https://github.com/middag-io/middag-php-moodle/commit/127939e918374a6cbdc78ea43a6f80986ef23258))
* **moodle:** align test fixture methodReturns keys with camelCase (A4 cascade) ([02a912e](https://github.com/middag-io/middag-php-moodle/commit/02a912e06279003dfcd8be6be0a6e1f6c2748927))
* **moodle:** drop inexistent &lt;item&gt; generic from ItemService PHPDoc ([4085d32](https://github.com/middag-io/middag-php-moodle/commit/4085d32620851ba14c416e4e1027756e81805d02))
* **moodle:** LSP-compliant set_require_capabilities + core\url stub ([c887aa5](https://github.com/middag-io/middag-php-moodle/commit/c887aa502e1722a345dc0b278800ea500f49b3fa))
* **moodle:** rename is_compatible callers in StaticsCollector (A4 cascade) ([ccd4b5d](https://github.com/middag-io/middag-php-moodle/commit/ccd4b5dd981a621d3023fb5c8590314a260277a5))
* **moodle:** rename method strings in StaticsCollector target map (A4 cascade) ([c659c88](https://github.com/middag-io/middag-php-moodle/commit/c659c88ea263e563a0c644c24b10b19e4279299f))
* **moodle:** rename remaining get_name() call sites in StaticsCollector (A4 cascade) ([3e31e36](https://github.com/middag-io/middag-php-moodle/commit/3e31e368b47b0a0a8442f832cb2c200c173d9021))
* **moodle:** rename remaining get_name() test fixture calls (A4 cascade) ([62bd4b3](https://github.com/middag-io/middag-php-moodle/commit/62bd4b38f3f164919974af07f64a734e4e1f66f0))
* pass --config=.php-rector.php so rector picks up the project config ([b1c671d](https://github.com/middag-io/middag-php-moodle/commit/b1c671d506b3a76d680873269f7eec5441bf891c))
* **pdf:** A2.A.1 TcpdfAdapter — update import to Middag\Core\Contract\PdfAdapterInterface (D-002) ([ee74031](https://github.com/middag-io/middag-php-moodle/commit/ee7403100917cbfb143d549587c427420457c1a9))
* **persistence:** A2.B.6 MigrationOrchestrator — remove FrameworkRunner, use CoreRunner::make_from_platform ([e4de7ad](https://github.com/middag-io/middag-php-moodle/commit/e4de7adf5e6229bbf85f82f342810fdae25bb76a))
* **query:** A2.G moodle — AbstractTableRepository use MiddagItemQueryBuilder, update phpstan baseline AuditScope namespace ([818ff99](https://github.com/middag-io/middag-php-moodle/commit/818ff99b008a4256932246d58ddfd407bfbddda3))
* **service:** A2.0 type narrow — MoodleExtensionInterface in hydrate/settings-tree ([38d8dc6](https://github.com/middag-io/middag-php-moodle/commit/38d8dc62f0e236f5eb2c053214704327328afb98))
* **service:** restore LicenseManager + LicenseService accidentally deleted in B-044 commit ([c718ac3](https://github.com/middag-io/middag-php-moodle/commit/c718ac3370c312f54725abd59984601479ad4357))
* **test:** add bootstrap with Moodle stubs and framework interface stubs ([b059b7c](https://github.com/middag-io/middag-php-moodle/commit/b059b7c80e34071c9b41e4dd30945a7d87c9ae29))

## [0.1.1](https://github.com/middag-io/middag-php-moodle/compare/v0.1.0...v0.1.1) (2026-05-13)


### Bug Fixes

* use semver constraints for internal deps ([d1c43ff](https://github.com/middag-io/middag-php-moodle/commit/d1c43ffacba715a64deb97cfdeba701b89aaf5b4))
