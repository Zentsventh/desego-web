<?php

namespace Tests\Feature;

use App\Support\Desego\SqlDump;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PDO;
use Tests\TestCase;

class InertiaPagesTest extends TestCase
{
    private static ?PDO $server = null;

    private static ?string $testDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        $config = config('database.connections.mysql');

        if (self::$testDatabase === null) {
            self::$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            self::$testDatabase = 'desego_test_'.bin2hex(random_bytes(6));
            self::$server->exec('CREATE DATABASE `'.self::$testDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }

        config(['database.default' => 'mysql', 'database.connections.mysql.database' => self::$testDatabase]);
        DB::purge('mysql');

        if (! DB::getSchemaBuilder()->hasTable('products')) {
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

            $pdo = DB::connection()->getPdo();
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach (app(SqlDump::class)->statements(file_get_contents(database_path('desego/reference-data.sql'))) as $statement) {
                $pdo->exec($statement);
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null && preg_match('/^desego_test_[a-f0-9]{12}$/', self::$testDatabase ?? '')) {
            self::$server->exec('DROP DATABASE `'.self::$testDatabase.'`');
        }
        self::$server = null;
        self::$testDatabase = null;
        parent::tearDownAfterClass();
    }

    public function test_store_home_renders_the_catalog_with_inertia(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('csrf-token', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Restaurant/Store')
                ->has('catalog.categories')
                ->has('catalog.products')
                ->has('catalog.branch')
                ->where('catalog.delivery_fee', 5)
                ->has('catalog.promotions'));
    }

    public function test_store_products_expose_only_public_fields(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('catalog.products.0', fn (Assert $product) => $product
                ->hasAll([
                    'id',
                    'code',
                    'slug',
                    'name',
                    'description',
                    'price',
                    'emoji',
                    'is_featured',
                    'is_popular',
                    'is_new',
                    'included_items',
                    'image_url',
                    'category_id',
                    'category',
                    'category_slug',
                    'allergens',
                    'can_order',
                    'image',
                    'recipe_count',
                    'review',
                    'gallery',
                    'option_groups',
                ])));
    }

    public function test_admin_panel_renders_login_when_there_is_no_session(): void
    {
        $this->get('/admin')->assertRedirect('/ingresar?staff=1');
    }

    public function test_storefront_api_still_serves_the_catalog_to_the_android_app(): void
    {
        $this->getJson('/api/storefront.php?view=products')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['categories', 'products', 'delivery_fee', 'csrfToken']);
    }

    public function test_health_and_mobile_endpoints_respond_for_android_app(): void
    {
        $this->getJson('/health.php')
            ->assertOk()
            ->assertJson([
                'service' => 'desego',
                'database' => 'connected',
            ]);

        $this->getJson('/api/mobile.php')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['authenticated', 'user', 'csrfToken']);
    }

    public function test_favicon_is_present_and_referenced_in_html_views(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('images/favicon_desego.png', false)
            ->assertSee('favicon.ico', false);

        $this->assertFileExists(public_path('images/favicon_desego.png'));
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path('favicon.png'));
        $this->assertGreaterThan(0, filesize(public_path('favicon.ico')));
    }
}
