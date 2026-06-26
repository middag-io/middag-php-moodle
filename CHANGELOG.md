# Changelog

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
