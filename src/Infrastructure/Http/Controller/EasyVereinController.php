<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\AuthService;
use App\Application\Port\UserRepositoryInterface;
use App\Infrastructure\Http\Auth\EasyVereinProvider;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\TemplateRenderer;
use App\Infrastructure\Persistence\SqliteEasyVereinIdentityRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final readonly class EasyVereinController
{
    public function __construct(
        private EasyVereinProvider $provider,
        private SqliteEasyVereinIdentityRepository $identities,
        private UserRepositoryInterface $users,
        private AuthService $auth,
    ) {
    }

    public function account(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $_SESSION['easyverein_csrf'] ??= bin2hex(random_bytes(32));
        return TemplateRenderer::render($response->withHeader('Cache-Control', 'no-store'), 'auth/easyverein.html.php', [
            'email' => Session::userEmail(),
            'enabled' => $this->provider->enabled(),
            'linked' => $this->identities->hasIdentity((int) Session::userId()),
            'csrf' => $_SESSION['easyverein_csrf'],
            'flash' => Session::flash('info'),
        ]);
    }

    public function link(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $csrf = $body['csrf'] ?? null;
        if (!is_string($csrf) || !isset($_SESSION['easyverein_csrf'])
            || !hash_equals($_SESSION['easyverein_csrf'], $csrf)) {
            return $response->withStatus(403);
        }
        try {
            // Re-enter the local password before adding a new authentication method.
            $password = $body['password'] ?? '';
            if (!is_string($password)) {
                throw new RuntimeException('Invalid password');
            }
            $user = $this->auth->login((string) Session::userEmail(), $password);
            if ($user->id() !== Session::userId()) {
                throw new RuntimeException('Account changed');
            }
            return $this->begin($response, (int) $user->id());
        } catch (Throwable) {
            $this->clearFlow();
            Session::flash('info', 'Verknüpfung nicht gestartet. Bitte prüfe dein bisheriges Passwort und versuche es erneut.');
            return $this->redirect($response, '/account/easyverein');
        }
    }

    public function unlink(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $csrf = $body['csrf'] ?? null;
        if (!is_string($csrf) || !isset($_SESSION['easyverein_csrf'])
            || !hash_equals($_SESSION['easyverein_csrf'], $csrf)) {
            return $response->withStatus(403)->withHeader('Cache-Control', 'no-store');
        }
        try {
            // Verify the fallback login before removing an authentication method.
            $password = $body['password'] ?? '';
            if (!is_string($password)) {
                throw new RuntimeException('Invalid password');
            }
            $user = $this->auth->login((string) Session::userEmail(), $password);
            if ($user->id() !== Session::userId()) {
                throw new RuntimeException('Account changed');
            }
            $this->identities->unlink((int) $user->id());
            $this->clearFlow();
            unset($_SESSION['easyverein_csrf']);
            Session::flash('info', 'Die easyVerein-Verknüpfung wurde entfernt. Du kannst dich weiterhin mit E-Mail und Passwort anmelden.');
        } catch (Throwable) {
            Session::flash('info', 'Verknüpfung nicht entfernt. Bitte prüfe dein bisheriges Kalkulator-Passwort und versuche es erneut.');
        }
        return $this->redirect($response, '/account/easyverein');
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            return $this->begin($response, null);
        } catch (Throwable) {
            $this->clearFlow();
            Session::flash('info', 'easyVerein-Anmeldung derzeit nicht möglich. Du kannst dich weiterhin mit E-Mail und Passwort anmelden.');
            return $this->redirect($response, '/login');
        }
    }

    private function begin(ResponseInterface $response, ?int $linkUserId): ResponseInterface
    {
        $this->clearFlow();
        $_SESSION['easyverein_flow'] = ['started' => time(), 'link_user_id' => $linkUserId];
        return $this->redirect($response, $this->provider->begin());
    }

    public function callback(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $flow = $_SESSION['easyverein_flow'] ?? null;
        $target = is_array($flow) && ($flow['link_user_id'] ?? null) !== null ? '/account/easyverein' : '/login';
        try {
            $query = $request->getQueryParams();
            $state = $query['state'] ?? null;
            $code = $query['code'] ?? null;
            $storedState = $_SESSION['openid_connect_state'] ?? null;
            if (!is_array($flow) || time() - $flow['started'] > 600 || isset($query['error'])
                || !is_string($code) || $code === '' || !is_string($state) || !is_string($storedState)
                || !hash_equals($storedState, $state)) {
                throw new RuntimeException('Invalid or expired callback');
            }
            // Consume the application flow before making any network requests.
            unset($_SESSION['easyverein_flow']);
            $subject = $this->provider->complete(['code' => $code, 'state' => $state]);
            if ($flow['link_user_id'] !== null) {
                $userId = $flow['link_user_id'];
                if (Session::userId() !== $userId || $this->users->findById($userId) === null) {
                    throw new RuntimeException('Session changed');
                }
                $this->identities->link($userId, EasyVereinProvider::ISSUER, $subject);
                Session::flash('info', 'easyVerein ist verknüpft. Du kannst jetzt beide Anmeldewege nutzen.');
                return $this->redirect($response, '/account/easyverein');
            }
            $userId = $this->identities->findUserId(EasyVereinProvider::ISSUER, $subject);
            $user = $userId === null ? null : $this->users->findById($userId);
            if ($user === null) {
                Session::flash('info', 'Bitte melde dich zuerst mit E-Mail und Passwort an und verknüpfe dein Konto unter „Mein Konto“.');
                return $this->redirect($response, '/login');
            }
            Session::login((int) $user->id(), $user->email(), $user->role());
            return $this->redirect($response, '/');
        } catch (Throwable) {
            Session::flash('info', 'easyVerein-Anmeldung oder Verknüpfung fehlgeschlagen. Bitte erneut starten. Die bisherige Anmeldung bleibt verfügbar.');
            return $this->redirect($response, $target);
        } finally {
            $this->clearFlow();
        }
    }

    private function clearFlow(): void
    {
        unset($_SESSION['easyverein_flow'], $_SESSION['openid_connect_state'],
            $_SESSION['openid_connect_nonce'], $_SESSION['openid_connect_code_verifier']);
    }

    private function redirect(ResponseInterface $response, string $location): ResponseInterface
    {
        return $response->withStatus(303)->withHeader('Location', $location)->withHeader('Cache-Control', 'no-store');
    }
}
