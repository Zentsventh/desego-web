<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class AdminAccessAndChatbotTest extends TestCase
{
    use CreatesRestaurantDatabase;

    private function loginAs(User $user): void
    {
        $this->withSession(['user_id' => $user->id, 'auth_password_fingerprint' => hash_hmac('sha256', $user->password_hash, (string) config('app.key'))]);
    }

    private function administrator(): User
    {
        return User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
    }

    public function test_administrator_can_open_roles_and_user_assignment(): void
    {
        $this->loginAs($this->administrator());
        $this->get('/admin/seccion/roles')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Restaurant/Admin')->where('section', 'roles')->has('data.roles')->has('data.permissions'));
        $this->get('/admin/seccion/usuarios')->assertOk()->assertInertia(fn (Assert $page) => $page->where('section', 'usuarios')->has('data.users')->has('data.roles'));
    }

    public function test_administrator_can_create_duplicate_and_edit_role_permissions(): void
    {
        $this->loginAs($this->administrator());
        $this->post('/admin/acciones/roles', ['action' => 'create', 'name' => 'Supervisor de sala', 'permissions' => ['dashboard.view', 'orders.view']])->assertRedirect()->assertSessionHasNoErrors();
        $roleId = DB::table('roles')->where('code', 'supervisor_de_sala')->value('id');
        $this->assertNotNull($roleId);
        $this->assertSame(2, DB::table('role_permissions')->where('role_id', $roleId)->count());
        $this->post('/admin/acciones/roles', ['action' => 'update', 'id' => $roleId, 'name' => 'Supervisor de sala', 'permissions' => ['orders.view']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('role_permissions')->where('role_id', $roleId)->count());
        $this->post('/admin/acciones/roles', ['action' => 'create', 'name' => 'Supervisor copia', 'permissions' => ['orders.view']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['event_code' => 'roles.updated']);
        $changes = json_decode(DB::table('audit_logs')->where('event_code', 'roles.updated')->value('details'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertContains('dashboard.view', $changes['permissions_before']);
        $this->assertSame(['orders.view'], $changes['permissions_after']);
    }

    public function test_administrator_assigns_a_role_and_permissions_change_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->loginAs($this->administrator());
        $cashier = DB::table('roles')->where('code', 'cashier')->value('id');
        $this->post('/admin/acciones/users', ['action' => 'update', 'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role_id' => $cashier])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role_id' => $cashier]);
        $this->loginAs($user);
        $this->get('/admin/seccion/caja')->assertOk();
        $this->get('/admin/seccion/roles')->assertForbidden();
    }

    public function test_role_manager_cannot_edit_protected_administrator_role(): void
    {
        $this->loginAs($this->administrator());
        $role = DB::table('roles')->where('code', 'admin')->first();
        $this->post('/admin/acciones/roles', ['action' => 'update', 'id' => $role->id, 'name' => 'Administrador', 'permissions' => []])->assertSessionHasErrors('request');
        $this->assertGreaterThan(0, DB::table('role_permissions')->where('role_id', $role->id)->count());
    }

    public function test_chat_history_is_available_only_to_authorized_staff(): void
    {
        config(['services.gemini.api_key' => null, 'services.gemini.api_key_secondary' => null, 'services.groq.api_key' => null, 'services.groq.api_key_secondary' => null]);
        Http::preventStrayRequests();
        $id = $this->postJson('/chatbot/mensaje', ['message' => 'Hola'])->assertOk()->json('conversation_id');
        $this->getJson('/admin/chatbot/'.$id)->assertRedirect();
        $this->loginAs(User::factory()->create());
        $this->getJson('/admin/chatbot/'.$id)->assertForbidden();
        $this->postJson('/admin/chatbot/'.$id.'/tomar-control')->assertForbidden();
        $this->postJson('/admin/chatbot/'.$id.'/responder', ['message' => 'No autorizado'])->assertForbidden();
        $this->get('/admin/seccion/chatbot')->assertForbidden();
        $this->loginAs($this->administrator());
        $this->get('/admin/seccion/chatbot')->assertOk()->assertInertia(fn (Assert $page) => $page->where('section', 'chatbot')->has('data.conversations.data', 1));
        $this->getJson('/admin/chatbot/'.$id)->assertOk()->assertJsonCount(2, 'messages')->assertJsonPath('messages.0.content', 'Hola')->assertJsonMissingPath('conversation.owner_hash');
    }

    public function test_staff_can_take_over_reply_and_return_a_chat_to_the_ai(): void
    {
        config(['services.gemini.api_key' => null, 'services.gemini.api_key_secondary' => null]);
        Http::preventStrayRequests();
        $id = $this->postJson('/chatbot/mensaje', ['message' => 'Quiero hablar con una persona'])
            ->assertOk()->assertJsonPath('handoff_status', 'requested')->json('conversation_id');
        $staff = $this->administrator();
        $this->loginAs($staff);

        $this->postJson('/admin/chatbot/'.$id.'/tomar-control')
            ->assertOk()
            ->assertJson(['support_status' => 'human', 'assigned_staff_id' => $staff->id]);
        $this->postJson('/admin/chatbot/'.$id.'/responder', ['message' => 'Hola, soy un asesor.'])
            ->assertOk()
            ->assertJsonPath('message.source', 'staff');

        $messageCount = DB::table('chatbot_messages')->where('conversation_id', $id)->count();
        $this->postJson('/chatbot/mensaje', ['message' => 'Gracias, ¿puedo continuar?', 'conversation_id' => $id])
            ->assertOk()
            ->assertJson(['handoff_status' => 'human']);
        $this->assertSame($messageCount + 1, DB::table('chatbot_messages')->where('conversation_id', $id)->count());
        Http::assertNothingSent();

        $this->postJson('/admin/chatbot/'.$id.'/devolver-ia')
            ->assertOk()
            ->assertJson(['support_status' => 'ai']);
        $this->assertDatabaseHas('chatbot_conversations', ['id' => $id, 'support_status' => 'ai', 'assigned_staff_id' => null]);
    }

    public function test_staff_can_mark_customer_messages_read_and_see_unread_count(): void
    {
        config(['services.gemini.api_key' => null, 'services.gemini.api_key_secondary' => null]);
        Http::preventStrayRequests();
        $id = $this->postJson('/chatbot/mensaje', ['message' => 'Hola, necesito ayuda'])->assertOk()->json('conversation_id');
        $messageId = (int) DB::table('chatbot_messages')->where('conversation_id', $id)->where('role', 'user')->value('id');
        $this->loginAs($this->administrator());

        $this->get('/admin/seccion/chatbot')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('section', 'chatbot')
            ->where('data.conversations.data.0.unread_count', '1'));
        $this->postJson('/admin/chatbot/'.$id.'/leido', ['message_id' => $messageId])->assertOk()->assertJson(['ok' => true]);
        $this->assertNotNull(DB::table('chatbot_messages')->where('id', $messageId)->value('read_at'));
        $history = $this->getJson('/admin/chatbot/'.$id)->assertOk()->assertJsonPath('messages.0.id', $messageId);
        $this->assertNotNull($history->json('messages.0.read_at'));
    }

    public function test_customer_cannot_open_any_admin_access_or_chat_module(): void
    {
        $this->postJson('/api/mobile/register', ['name' => 'Cliente', 'email' => 'customer@example.test', 'password' => 'SecureCustomer123', 'password_confirmation' => 'SecureCustomer123', 'phone' => '999111222'])->assertCreated();
        foreach (['roles', 'usuarios', 'chatbot'] as $section) {
            $this->get('/admin/seccion/'.$section)->assertForbidden();
        }
    }

    public function test_mobile_authentication_is_customer_only_and_has_no_admin_dashboard(): void
    {
        $admin = $this->administrator();
        $this->postJson('/api/mobile/login', ['email' => $admin->email, 'password' => 'password'])->assertForbidden()->assertSessionMissing('user_id');
        $this->assertDatabaseMissing('customers', ['user_id' => $admin->id]);
        $this->loginAs($admin);
        $this->getJson('/api/mobile/session')->assertOk()->assertJson(['authenticated' => false, 'user' => null]);
        $this->getJson('/api/mobile/dashboard')->assertForbidden();
        $this->get('/admin')->assertOk();
    }

    public function test_non_admin_cannot_demote_or_deactivate_an_administrator(): void
    {
        $target = $this->administrator();
        $roleId = DB::table('roles')->where('code', 'manager')->value('id');
        foreach (['users.update', 'users.deactivate'] as $code) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('code', $code)->value('id')]);
        }
        $this->loginAs(User::factory()->create(['role_id' => $roleId]));
        $this->post('/admin/acciones/users', ['action' => 'update', 'id' => $target->id, 'name' => $target->name, 'email' => $target->email, 'role_id' => $roleId])->assertForbidden();
        $this->post('/admin/acciones/users', ['action' => 'status', 'id' => $target->id, 'status' => 'inactive'])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role_id' => $target->role_id, 'status' => 'active']);
    }

    public function test_chat_history_paginates_messages_without_losing_older_turns(): void
    {
        config(['services.gemini.api_key' => null, 'services.gemini.api_key_secondary' => null, 'services.groq.api_key' => null, 'services.groq.api_key_secondary' => null]);
        Http::preventStrayRequests();
        $id = $this->postJson('/chatbot/mensaje', ['message' => 'Hola'])->assertOk()->json('conversation_id');
        DB::table('chatbot_messages')->insert(array_map(fn (int $turn): array => [
            'conversation_id' => $id, 'role' => 'user', 'content' => 'Mensaje '.$turn, 'created_at' => now(),
        ], range(1, 105)));
        $this->loginAs($this->administrator());
        $response = $this->getJson('/admin/chatbot/'.$id)->assertOk()->assertJsonCount(100, 'messages')->assertJsonPath('has_more', true);
        $before = $response->json('messages.0.id');
        $this->getJson('/admin/chatbot/'.$id.'?before='.$before)->assertOk()->assertJsonCount(7, 'messages')->assertJsonPath('has_more', false)->assertJsonPath('messages.0.content', 'Hola');
    }

    public function test_admin_operations_create_records_through_the_panel_routes(): void
    {
        $this->loginAs($this->administrator());
        $this->post('/admin/acciones/customers', ['action' => 'customer.create', 'full_name' => 'Cliente del panel', 'document_type' => 'SIN_DOCUMENTO'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['full_name' => 'Cliente del panel']);
        $this->post('/admin/acciones/workforce', ['action' => 'employee.save', 'employee_code' => 'EMP-TEST', 'full_name' => 'Personal del panel', 'job_title' => 'Cocina'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', ['employee_code' => 'EMP-TEST']);
        $this->post('/admin/acciones/loyalty', ['action' => 'reward.save', 'reward_code' => 'PREMIO-TEST', 'name' => 'Beneficio del panel', 'reward_type' => 'discount', 'points_cost' => 100, 'discount_amount' => 15, 'starts_at' => now('UTC')->format('Y-m-d\TH:i')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('loyalty_rewards', ['reward_code' => 'PREMIO-TEST']);
        $this->post('/admin/acciones/promotions', ['action' => 'create', 'name' => 'Promoción del panel', 'code' => 'PRUEBA10', 'promotion_type' => 'coupon', 'discount_type' => 'percentage', 'discount_value' => 10, 'starts_at' => now('UTC')->format('Y-m-d\TH:i')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('promotions', ['code' => 'PRUEBA10']);
        $this->post('/admin/acciones/purchasing', ['action' => 'supplier.save', 'name' => 'Proveedor del panel'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('suppliers', ['name' => 'Proveedor del panel']);
        $this->post('/admin/acciones/communications', ['action' => 'support.create', 'subject' => 'Consulta del panel', 'message' => 'Necesito revisar una consulta del cliente.', 'category' => 'orders'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_tickets', ['subject' => 'Consulta del panel']);
    }

    public function test_cash_catalog_inventory_and_reservations_work_through_panel_routes(): void
    {
        $this->loginAs($this->administrator());
        $this->post('/admin/acciones/cash', ['action' => 'open', 'opening_amount' => 200])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/admin/acciones/cash', ['action' => 'close', 'counted_closing' => 200])->assertRedirect()->assertSessionHasNoErrors();
        $ingredient = DB::table('ingredients')->first();
        $this->post('/admin/acciones/inventory', ['action' => 'adjust', 'ingredient_id' => $ingredient->id, 'movement_type' => 'entry', 'quantity' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals((float) $ingredient->stock_quantity + 1, (float) DB::table('ingredients')->where('id', $ingredient->id)->value('stock_quantity'));
        $this->post('/admin/acciones/catalog', ['action' => 'product.create', 'name' => 'Plato del panel', 'category_id' => DB::table('categories')->value('id'), 'price' => 35, 'status' => 'available', 'ingredients' => [['ingredient_id' => $ingredient->id, 'quantity' => .25]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['name' => 'Plato del panel']);
        $this->post('/admin/acciones/reservations', ['action' => 'reservation.create', 'table_id' => DB::table('restaurant_tables')->value('id'), 'customer_name' => 'Reserva del panel', 'guest_count' => 2, 'reservation_at' => now('America/Lima')->addDay()->setTime(13, 0)->format('Y-m-d H:i'), 'duration_minutes' => 90])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservations', ['customer_name' => 'Reserva del panel']);
    }

    public function test_catalog_management_and_employee_accounts_are_connected_to_the_panel(): void
    {
        $this->loginAs($this->administrator());
        foreach (['categorias', 'insumos', 'alergenos'] as $section) {
            $this->get('/admin/seccion/'.$section)->assertOk();
        }
        $this->post('/admin/acciones/catalog', ['action' => 'category.create', 'name' => 'Categoría del panel'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', ['name' => 'Categoría del panel']);
        $this->post('/admin/acciones/catalog', ['action' => 'allergen.create', 'name' => 'Alérgeno del panel'])->assertRedirect()->assertSessionHasNoErrors();
        $allergen = DB::table('allergens')->where('name', 'Alérgeno del panel')->value('id');
        $this->post('/admin/acciones/catalog', ['action' => 'ingredient.create', 'name' => 'Ingrediente del panel', 'unit' => 'kg', 'initial_stock' => 1, 'cost_per_unit' => 12, 'allergen_ids' => [$allergen]])->assertRedirect()->assertSessionHasNoErrors();
        $ingredient = DB::table('ingredients')->where('name', 'Ingrediente del panel')->value('id');
        $this->assertDatabaseHas('ingredient_allergens', ['ingredient_id' => $ingredient, 'allergen_id' => $allergen]);
        $account = User::factory()->create(['role_id' => DB::table('roles')->where('code', 'waiter')->value('id')]);
        $this->post('/admin/acciones/workforce', ['action' => 'employee.save', 'employee_code' => 'EMP-LINK', 'full_name' => 'Personal vinculado', 'job_title' => 'Mozo', 'user_id' => $account->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', ['employee_code' => 'EMP-LINK', 'user_id' => $account->id]);
    }

    public function test_long_custom_role_names_fit_the_database_and_remain_unique(): void
    {
        $this->loginAs($this->administrator());
        $name = str_repeat('Supervisor ', 6);
        $payload = ['action' => 'create', 'name' => $name, 'permissions' => ['dashboard.view']];
        $this->post('/admin/acciones/roles', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $code = DB::table('roles')->where('name', trim($name))->value('code');
        $this->assertNotNull($code);
        $this->assertLessThanOrEqual(40, strlen($code));
        $this->post('/admin/acciones/roles', $payload)->assertSessionHasErrors('request');
        $this->assertSame(1, DB::table('roles')->where('code', $code)->count());
    }

    public function test_customers_can_still_login_and_use_the_mobile_session(): void
    {
        $this->postJson('/api/mobile/register', ['name' => 'Cliente móvil', 'email' => 'mobile@example.test', 'password' => 'SecureCustomer123', 'password_confirmation' => 'SecureCustomer123', 'phone' => '999111222'])->assertCreated();
        $this->postJson('/api/mobile/logout')->assertOk();
        $this->postJson('/api/mobile/login', ['email' => 'mobile@example.test', 'password' => 'SecureCustomer123'])->assertOk()->assertJsonPath('user.role_code', 'customer');
        $this->getJson('/api/mobile/session')->assertOk()->assertJsonPath('authenticated', true)->assertJsonPath('user.role_code', 'customer');
        $this->get('/admin')->assertForbidden();
    }

    public function test_guest_conversation_is_linked_to_the_customer_without_using_staff_identity(): void
    {
        config(['services.gemini.api_key' => null, 'services.gemini.api_key_secondary' => null, 'services.groq.api_key' => null, 'services.groq.api_key_secondary' => null]);
        Http::preventStrayRequests();
        $id = $this->postJson('/chatbot/mensaje', ['message' => 'Hola'])->assertOk()->json('conversation_id');
        $customer = $this->postJson('/api/mobile/register', ['name' => 'Cliente del chat', 'email' => 'chat@example.test', 'password' => 'SecureCustomer123', 'password_confirmation' => 'SecureCustomer123', 'phone' => '999111222'])->assertCreated()->json('user.id');
        $this->postJson('/chatbot/mensaje', ['message' => 'Quiero reservar', 'conversation_id' => $id])->assertOk();
        $this->assertDatabaseHas('chatbot_conversations', [
            'id' => $id,
            'customer_id' => DB::table('customers')->where('user_id', $customer)->value('id'),
            'user_id' => null,
            'title' => 'Cliente del chat',
        ]);
        $this->assertDatabaseCount('chatbot_conversations', 1);
    }

    public function test_guest_messages_without_a_conversation_id_continue_the_same_open_chat(): void
    {
        config(['services.gemini.api_key' => null, 'services.gemini.api_key_secondary' => null, 'services.groq.api_key' => null, 'services.groq.api_key_secondary' => null]);
        Http::preventStrayRequests();

        $firstId = $this->postJson('/chatbot/mensaje', ['message' => 'Hola'])->assertOk()->json('conversation_id');
        $secondId = $this->postJson('/chatbot/mensaje', ['message' => '¿Qué platos tienen?'])->assertOk()->json('conversation_id');

        $this->assertSame($firstId, $secondId);
        $this->assertDatabaseCount('chatbot_conversations', 1);
        $this->assertDatabaseCount('chatbot_messages', 4);
    }

    public function test_guest_chat_titles_use_sequential_visitor_codes_instead_of_message_or_session_text(): void
    {
        config(['services.gemini.api_key' => null, 'services.gemini.api_key_secondary' => null, 'services.groq.api_key' => null, 'services.groq.api_key_secondary' => null]);
        Http::preventStrayRequests();

        $this->withSession(['chatbot_owner' => 'guest-number-one'])
            ->postJson('/chatbot/mensaje', ['message' => 'Este mensaje no debe aparecer como título'])->assertOk();
        $this->withSession(['chatbot_owner' => 'guest-number-two'])
            ->postJson('/chatbot/mensaje', ['message' => 'Ni tampoco este texto largo'])->assertOk();

        $expectedCodes = DB::table('chatbot_visitors')->orderBy('id')->pluck('id')
            ->map(fn (int $id): string => 'v'.str_pad((string) $id, 7, '0', STR_PAD_LEFT))
            ->all();
        $this->loginAs($this->administrator());
        $response = $this->get('/admin/seccion/chatbot')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.conversations.total', 2)
            ->where('data.conversations.data', function ($conversations) use ($expectedCodes): bool {
                $actualCodes = array_column($conversations->all(), 'customer_name');
                sort($expectedCodes);
                sort($actualCodes);

                return $expectedCodes === $actualCodes;
            }));
        $response->assertDontSee('Ni tampoco este texto largo')->assertDontSee('Este mensaje no debe aparecer como título');
    }

    public function test_admin_can_close_and_reopen_all_sessions_for_the_same_customer(): void
    {
        config(['services.gemini.api_key' => null, 'services.gemini.api_key_secondary' => null, 'services.groq.api_key' => null, 'services.groq.api_key_secondary' => null]);
        Http::preventStrayRequests();
        $customerId = DB::table('customers')->insertGetId([
            'full_name' => 'Cliente unificado',
            'document_type' => 'SIN_DOCUMENTO',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $firstId = $this->withSession(['storefront_customer_id' => $customerId, 'chatbot_owner' => 'first-browser-session'])
            ->postJson('/chatbot/mensaje', ['message' => 'Hola'])->assertOk()->json('conversation_id');
        $secondId = $this->withSession(['storefront_customer_id' => $customerId, 'chatbot_owner' => 'second-browser-session'])
            ->postJson('/chatbot/mensaje', ['message' => 'Consulta desde otro dispositivo'])->assertOk()->json('conversation_id');
        $this->assertSame($firstId, $secondId);

        $otherSessionConversation = (string) Str::uuid();
        DB::table('chatbot_conversations')->insert([
            'id' => $otherSessionConversation,
            'owner_hash' => hash('sha256', 'older-browser-session'),
            'user_id' => null,
            'customer_id' => $customerId,
            'title' => 'Consulta anterior',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        DB::table('chatbot_messages')->insert([
            ['conversation_id' => $otherSessionConversation, 'role' => 'user', 'content' => 'Mensaje anterior', 'created_at' => now()->subDay()],
            ['conversation_id' => $otherSessionConversation, 'role' => 'assistant', 'content' => 'Respuesta anterior', 'created_at' => now()->subDay()],
        ]);

        $this->loginAs($this->administrator());
        $this->get('/admin/seccion/chatbot')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.conversations.total', 1)
            ->where('data.conversations.data.0.customer_name', 'Cliente unificado')
            ->where('data.conversations.data.0.message_count', 6));
        $this->postJson('/admin/chatbot/'.$firstId.'/cerrar')->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('chatbot_conversations', ['customer_id' => $customerId, 'archived_at' => null]);
        $this->get('/admin/seccion/chatbot')->assertOk()->assertInertia(fn (Assert $page) => $page->where('data.conversations.total', 0));
        $this->get('/admin/seccion/chatbot?status=closed')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.conversations.total', 1)
            ->where('data.filters.status', 'closed'));
        $this->postJson('/admin/chatbot/'.$secondId.'/reabrir')->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('chatbot_conversations', ['customer_id' => $customerId, 'archived_at' => null]);
    }

    public function test_message_after_a_closed_chat_starts_a_new_open_chat(): void
    {
        config(['services.gemini.api_key' => null, 'services.gemini.api_key_secondary' => null, 'services.groq.api_key' => null, 'services.groq.api_key_secondary' => null]);
        Http::preventStrayRequests();
        $session = ['chatbot_owner' => 'reopen-after-archive'];
        $oldId = $this->withSession($session)->postJson('/chatbot/mensaje', ['message' => 'Hola'])->assertOk()->json('conversation_id');
        $this->loginAs($this->administrator());
        $this->postJson('/admin/chatbot/'.$oldId.'/cerrar')->assertOk();

        $newId = $this->withSession($session)->postJson('/chatbot/mensaje', [
            'message' => 'Tengo otra consulta',
            'conversation_id' => $oldId,
        ])->assertOk()->json('conversation_id');

        $this->assertNotSame($oldId, $newId);
        $this->assertDatabaseHas('chatbot_conversations', ['id' => $oldId]);
        $this->assertDatabaseHas('chatbot_conversations', ['id' => $newId, 'archived_at' => null]);
    }

    public function test_administrator_can_view_support_tickets_with_attachments_and_update_status_and_reply(): void
    {
        $admin = $this->administrator();
        $customerUser = User::factory()->create([
            'name' => 'María Del Mar',
            'email' => 'maria@example.test',
        ]);

        $ticketId = DB::table('support_tickets')->insertGetId([
            'ticket_number' => 'TCK-000099',
            'created_by' => $customerUser->id,
            'subject' => 'Consulta por pedido con ceviche mixto',
            'category' => 'orders',
            'priority' => 'normal',
            'status' => 'open',
            'attachment_url' => '/storage/support/foto_pedido.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('support_ticket_messages')->insert([
            'ticket_id' => $ticketId,
            'author_id' => $customerUser->id,
            'author_name' => 'María Del Mar',
            'message' => 'Adjunto la imagen de mi comprobante para validar la entrega.',
            'attachment_url' => '/storage/support/comprobante_banco.pdf',
            'is_internal' => 0,
            'created_at' => now(),
        ]);

        $this->loginAs($admin);

        // 1. Verify /admin/seccion/soporte loads tickets with attachments and full messages
        $response = $this->get('/admin/seccion/soporte')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Restaurant/Admin')
            ->where('section', 'soporte')
            ->has('data.tickets')
            ->where('data.tickets.0.ticket_number', 'TCK-000099')
            ->where('data.tickets.0.attachment_url', '/storage/support/foto_pedido.jpg')
            ->where('data.tickets.0.creator_name', 'María Del Mar')
            ->where('data.tickets.0.creator_email', 'maria@example.test')
            ->where('data.tickets.0.messages.0.attachment_url', '/storage/support/comprobante_banco.pdf'));

        // 2. Administrator updates status to 'resolved' (Atendido / Completado) and replies
        $updateResponse = $this->post('/admin/acciones/communications', [
            'action' => 'support.update',
            'id' => $ticketId,
            'status' => 'resolved',
            'priority' => 'high',
            'assigned_to' => $admin->id,
            'message' => 'Estimada María, hemos validado tu caso y fue completado con éxito.',
            'is_internal' => 0,
        ]);
        $updateResponse->assertRedirect()->assertSessionHasNoErrors();

        // 3. Verify DB updates
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticketId,
            'status' => 'resolved',
            'priority' => 'high',
            'assigned_to' => $admin->id,
        ]);
        $this->assertNotNull(DB::table('support_tickets')->where('id', $ticketId)->value('resolved_at'));

        $this->assertDatabaseHas('support_ticket_messages', [
            'ticket_id' => $ticketId,
            'author_id' => $admin->id,
            'message' => 'Estimada María, hemos validado tu caso y fue completado con éxito.',
            'is_internal' => 0,
        ]);
    }

    public function test_admin_can_upload_image_using_image_or_file_key(): void
    {
        Storage::fake('public');
        $this->loginAs($this->administrator());

        $fileUpload = UploadedFile::fake()->image('plato_ceviche.jpg', 600, 400);
        $res1 = $this->postJson('/admin/subir-imagen', ['file' => $fileUpload])
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertStringContainsString('/uploads/products/', $res1->json('url'));

        $imageUpload = UploadedFile::fake()->image('plato_jalea.png', 600, 400);
        $res2 = $this->postJson('/admin/subir-imagen', ['image' => $imageUpload])
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertStringContainsString('/uploads/products/', $res2->json('url'));
    }

    public function test_admin_can_update_order_status_to_cancelled_or_paid_via_order_status_action(): void
    {
        $admin = $this->administrator();
        $this->loginAs($admin);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-TEST-999',
            'created_by' => $admin->id,
            'customer_name' => 'Cliente Prueba',
            'order_type' => 'Mesa',
            'table_label' => 'Mesa 1',
            'status' => 'Pendiente',
            'payment_status' => 'Pendiente',
            'total' => 45.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Mark as paid
        $this->post('/admin/acciones/orders', [
            'action' => 'status',
            'id' => $orderId,
            'status' => 'Preparando',
            'payment_status' => 'Pagado',
            'payment_method' => 'Efectivo',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'Preparando',
            'payment_status' => 'Pagado',
        ]);

        // 2. Mark as cancelado
        $unpaidOrderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-TEST-888',
            'created_by' => $admin->id,
            'customer_name' => 'Cliente Cancelar',
            'order_type' => 'Mesa',
            'table_label' => 'Mesa 2',
            'status' => 'Pendiente',
            'payment_status' => 'Pendiente',
            'total' => 30.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/admin/acciones/orders', [
            'action' => 'status',
            'id' => $unpaidOrderId,
            'status' => 'Cancelado',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $unpaidOrderId,
            'status' => 'Cancelado',
        ]);
        $this->assertNotNull(DB::table('orders')->where('id', $unpaidOrderId)->value('cancelled_at'));
    }

    public function test_admin_can_set_or_keep_reservation_status_as_pendiente(): void
    {
        $this->loginAs($this->administrator());
        $tableId = DB::table('restaurant_tables')->value('id') ?? DB::table('restaurant_tables')->insertGetId([
            'label' => 'Mesa 1',
            'table_number' => 1,
            'capacity' => 4,
            'zone' => 'Salón',
            'is_active' => 1,
            'operational_status' => 'available',
        ]);

        $resId = DB::table('reservations')->insertGetId([
            'reservation_code' => 'RES-TEST-123',
            'table_id' => $tableId,
            'customer_name' => 'Reserva Prueba',
            'customer_phone' => '999888777',
            'guest_count' => 2,
            'status' => 'Pendiente',
            'payment_status' => 'Pendiente',
            'reservation_at' => now()->addDay()->format('Y-m-d 14:00:00'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/admin/acciones/reservations', [
            'action' => 'reservation.status',
            'id' => $resId,
            'status' => 'Pendiente',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reservations', [
            'id' => $resId,
            'status' => 'Pendiente',
        ]);
    }

    public function test_admin_can_create_support_ticket_with_reservations_and_loyalty_categories(): void
    {
        $admin = $this->administrator();
        $this->loginAs($admin);

        $this->post('/admin/acciones/communications', [
            'action' => 'support.create',
            'subject' => 'Consulta sobre mesa y reserva',
            'category' => 'reservations',
            'priority' => 'normal',
            'message' => 'Necesito reubicar a comensales en la terraza.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Consulta sobre mesa y reserva',
            'category' => 'reservations',
        ]);

        $this->post('/admin/acciones/communications', [
            'action' => 'support.create',
            'subject' => 'Canje de puntos Club DeSego',
            'category' => 'loyalty',
            'priority' => 'high',
            'message' => 'Ajuste de puntos para cliente VIP.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Canje de puntos Club DeSego',
            'category' => 'loyalty',
        ]);
    }
}
