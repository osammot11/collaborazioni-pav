<?php

namespace Tests\Feature;

use App\Models\Collaboration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class McpIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private static array $keys;

    protected function setUp(): void
    {
        parent::setUp();
        if (! isset(self::$keys)) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            self::$keys = [$private, openssl_pkey_get_details($key)['key']];
        }
        config(['passport.private_key' => self::$keys[0], 'passport.public_key' => self::$keys[1]]);
    }

    private function loginAdmin(): User
    {
        $user = User::factory()->create(['password' => 'correct-password-123', 'is_admin' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password-123'])->assertRedirect('/integrazioni');

        return $user;
    }

    private function registration(): string
    {
        return $this->postJson('/oauth/register', ['client_name' => 'ChatGPT test',
            'redirect_uris' => [config('integrations.redirect_uris')[0]], 'token_endpoint_auth_method' => 'none'])
            ->assertCreated()->json('client_id');
    }

    private function authorization(string $client, string $verifier, string $scope = 'pipeline:read pipeline:write'): array
    {
        $params = ['client_id' => $client, 'redirect_uri' => config('integrations.redirect_uris')[0],
            'response_type' => 'code', 'scope' => $scope, 'state' => Str::random(40),
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256', 'resource' => config('integrations.resource')];
        $response = $this->get('/oauth/authorize?'.http_build_query($params))->assertOk()->assertSee('Autorizza collegamento');
        $authToken = $response->viewData('authToken');
        $approved = $this->post('/oauth/authorize', ['auth_token' => $authToken])->assertRedirect();
        parse_str(parse_url($approved->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame($params['state'], $query['state']);
        $this->assertSame(rtrim(config('app.url'), '/'), $query['iss']);

        return ['grant_type' => 'authorization_code', 'client_id' => $client, 'code' => $query['code'],
            'redirect_uri' => $params['redirect_uri'], 'code_verifier' => $verifier, 'resource' => $params['resource']];
    }

    private function credentials(string $scope = 'pipeline:read pipeline:write'): array
    {
        $this->loginAdmin();
        $client = $this->registration();
        $params = $this->authorization($client, Str::random(64), $scope);

        return ['client_id' => $client, ...$this->postJson('/oauth/token', $params)->assertOk()->json()];
    }

    private function rpc(string $name, array $arguments = [], ?string $token = null)
    {
        Auth::guard('api')->forgetUser();
        $headers = $token ? ['Authorization' => 'Bearer '.$token] : [];

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments]], $headers);
    }

    public function test_public_discovery_and_schema_do_not_expose_pipeline_data(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
            'protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]])->assertOk()->assertJsonPath('result.serverInfo.name', 'Produce a Value · Pipeline');
        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()->assertJsonPath('resource', config('integrations.resource'));
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->assertJsonPath('code_challenge_methods_supported.0', 'S256');
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertOk()->assertJsonCount(5, 'result.tools')->assertJsonPath('result.tools.0.securitySchemes.0.scopes.0', 'pipeline:read');
        $this->rpc('search_opportunities')->assertUnauthorized()->assertHeader('WWW-Authenticate',
            'Bearer resource_metadata="'.rtrim(config('app.url'), '/').'/.well-known/oauth-protected-resource/mcp"');
        $this->withSession(['collaborations_authorized' => true])->get('/integrazioni')->assertRedirect('/login');
    }

    public function test_redirects_pkce_resource_and_admin_role_are_enforced(): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'malicious', 'redirect_uris' => ['https://evil.example/callback']])->assertUnprocessable();
        $this->postJson('/oauth/token', ['resource' => 'https://evil.example/mcp'])->assertStatus(400)->assertJsonPath('error', 'invalid_target');
        $this->loginAdmin();
        $this->get('/oauth/authorize?resource='.urlencode(config('integrations.resource')))->assertStatus(400);
        $regular = User::factory()->create();
        $this->actingAs($regular)->get('/integrazioni')->assertForbidden();
        $this->actingAs($regular)->get('/oauth/authorize')->assertForbidden();
    }

    public function test_real_pkce_token_create_update_replay_conflict_refresh_and_revoke(): void
    {
        $auth = $this->credentials();
        $token = $auth['access_token'];
        $create = ['request_id' => (string) Str::uuid(), 'fields' => ['name' => 'Hotel Aurora', 'category' => 'Hotel',
            'monthly_revenue' => '250.00', 'one_time_revenue' => '1000.00', 'notes' => 'Nota originale',
            'contact_email' => 'hotel@example.com', 'follow_up_date' => '2026-11-20']];
        $created = $this->rpc('create_opportunity', $create, $token)->assertOk()->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.opportunity.name', 'Hotel Aurora');
        $id = $created->json('result.structuredContent.opportunity.id');
        $this->rpc('create_opportunity', $create, $token)->assertJsonPath('result.structuredContent.replayed', true);
        $this->assertDatabaseCount('collaborations', 1);
        $this->assertDatabaseCount('mcp_operations', 1);
        $this->rpc('get_opportunity', ['id' => $id], $token)->assertJsonPath('result.structuredContent.opportunity.revision', 1);
        $update = ['id' => $id, 'expected_revision' => 1, 'request_id' => (string) Str::uuid(),
            'fields' => ['pipeline_stage' => 'contratto', 'follow_up_date' => null]];
        $this->rpc('update_opportunity', $update, $token)->assertJsonPath('result.structuredContent.opportunity.revision', 2)
            ->assertJsonPath('result.structuredContent.opportunity.pipeline_outcome', 'vinto');
        $this->assertSame('Nota originale', Collaboration::find($id)->notes);
        $this->assertSame('250.00', Collaboration::find($id)->monthly_revenue);
        $this->assertNull(Collaboration::find($id)->follow_up_date);
        $this->assertDatabaseCount('pipeline_events', 2);
        $this->rpc('update_opportunity', $update, $token)->assertJsonPath('result.structuredContent.replayed', true);
        $update['request_id'] = (string) Str::uuid();
        $this->rpc('update_opportunity', $update, $token)->assertJsonPath('result.isError', true)->assertSee('CONFLICT');
        $create['fields']['name'] = 'Nome diverso';
        $this->rpc('create_opportunity', $create, $token)->assertJsonPath('result.isError', true);
        $this->rpc('search_opportunities', ['query' => 'Aurora', 'category' => 'Hotel'], $token)->assertJsonPath('result.structuredContent.total', 1);
        $refresh = ['grant_type' => 'refresh_token', 'client_id' => $auth['client_id'], 'refresh_token' => $auth['refresh_token'], 'resource' => config('integrations.resource')];
        $new = $this->postJson('/oauth/token', $refresh)->assertOk()->json();
        $this->rpc('pipeline_options', [], $new['access_token'])->assertJsonPath('result.isError', false);
        $this->get('/integrazioni')->assertOk()->assertSee('Hotel Aurora');
        $this->delete('/integrazioni/'.$auth['client_id'])->assertRedirect();
        $this->rpc('pipeline_options', [], $new['access_token'])->assertUnauthorized();
        $this->postJson('/oauth/token', [...$refresh, 'refresh_token' => $new['refresh_token']])->assertStatus(400);
    }

    public function test_read_only_scope_cannot_modify_and_unbound_tokens_are_rejected(): void
    {
        $auth = $this->credentials('pipeline:read');
        $this->rpc('search_opportunities', [], $auth['access_token'])->assertJsonPath('result.isError', false);
        $this->rpc('create_opportunity', ['request_id' => (string) Str::uuid(), 'fields' => ['name' => 'No']], $auth['access_token'])
            ->assertJsonPath('result.isError', true)->assertJsonStructure(['result' => ['_meta' => ['mcp/www_authenticate']]]);
        DB::table('mcp_token_resources')->delete();
        $this->rpc('search_opportunities', [], $auth['access_token'])->assertUnauthorized();
        $this->assertDatabaseCount('collaborations', 0);
    }

    public function test_invalid_fields_and_web_revision_changes_are_detected(): void
    {
        $auth = $this->credentials();
        foreach ([['monthly_revenue' => '-1'], ['one_time_revenue' => '1.234'], ['demo_date' => '2026-02-31'],
            ['pipeline_stage' => 'invalid'], ['pipeline_outcome' => 'vinto'], ['contact_email' => 'no'], ['revision' => 5]] as $fields) {
            $this->rpc('create_opportunity', ['request_id' => (string) Str::uuid(), 'fields' => ['name' => 'Test', ...$fields]], $auth['access_token'])
                ->assertJsonPath('result.isError', true);
        }
        $created = $this->rpc('create_opportunity', ['request_id' => (string) Str::uuid(), 'fields' => ['name' => 'Test']], $auth['access_token']);
        $id = $created->json('result.structuredContent.opportunity.id');
        $item = Collaboration::findOrFail($id);
        $item->update(['notes' => 'Modifica via web']);
        $this->assertSame(2, $item->revision);
        $this->rpc('update_opportunity', ['id' => $id, 'expected_revision' => 1, 'request_id' => (string) Str::uuid(),
            'fields' => ['notes' => 'Sovrascrittura']], $auth['access_token'])->assertJsonPath('result.isError', true);
        $this->assertSame('Modifica via web', $item->fresh()->notes);
        $this->assertDatabaseCount('mcp_operations', 1);
    }

    public function test_wrong_pkce_verifier_does_not_issue_a_token(): void
    {
        $this->loginAdmin();
        $params = $this->authorization($this->registration(), Str::random(64));
        $params['code_verifier'] = Str::random(64);
        $this->postJson('/oauth/token', $params)->assertStatus(400);
        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_consent_denial_keeps_state_and_issuer_and_does_not_issue_tokens(): void
    {
        $this->loginAdmin();
        $params = ['client_id' => $this->registration(), 'redirect_uri' => config('integrations.redirect_uris')[0],
            'response_type' => 'code', 'scope' => 'pipeline:read', 'state' => 'decline-test',
            'code_challenge' => str_repeat('A', 43), 'code_challenge_method' => 'S256', 'resource' => config('integrations.resource')];
        $response = $this->get('/oauth/authorize?'.http_build_query($params))->assertOk();
        $denied = $this->delete('/oauth/authorize', ['auth_token' => $response->viewData('authToken')])->assertRedirect();
        parse_str(parse_url($denied->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('access_denied', $query['error']);
        $this->assertSame('decline-test', $query['state']);
        $this->assertSame(rtrim(config('app.url'), '/'), $query['iss']);
        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_search_pagination_and_closed_followups_and_literal_wildcards(): void
    {
        $auth = $this->credentials();
        foreach (range(1, 3) as $number) {
            Collaboration::create(['name' => 'Hotel '.$number, 'category' => 'Hotel', 'monthly_revenue' => 0,
                'one_time_revenue' => 0, 'status' => 'forse', 'follow_up_date' => today()->subDay()]);
        }
        Collaboration::create(['name' => 'Hotel 100%', 'monthly_revenue' => 0, 'one_time_revenue' => 0, 'status' => 'pagato',
            'pipeline_stage' => 'contratto', 'pipeline_outcome' => 'vinto', 'follow_up_date' => today()->subDay()]);
        $this->rpc('search_opportunities', ['follow_up' => 'overdue', 'per_page' => 2, 'page' => 2], $auth['access_token'])
            ->assertJsonPath('result.structuredContent.total', 3)->assertJsonPath('result.structuredContent.pages', 2)
            ->assertJsonCount(1, 'result.structuredContent.items');
        $this->rpc('search_opportunities', ['query' => '%'], $auth['access_token'])->assertJsonPath('result.structuredContent.total', 1);
        $this->rpc('search_opportunities', ['per_page' => 51], $auth['access_token'])->assertJsonPath('result.isError', true);
    }

    public function test_admin_login_logout_and_csrf_protection(): void
    {
        $user = User::factory()->create(['password' => 'correct-password-123', 'is_admin' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password-123'])->assertRedirect('/integrazioni');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/access');
        $this->assertGuest();
        $this->get('/integrazioni')->assertRedirect('/login');
        // Enable real request-forgery checks, normally bypassed by Laravel in test mode.
        app()->instance('env', 'local');
        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password-123'])->assertStatus(419);
        $this->post('/oauth/authorize', ['auth_token' => 'invented'])->assertStatus(419);
    }
}
