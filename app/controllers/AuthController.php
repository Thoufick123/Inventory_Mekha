<?php
// app/controllers/AuthController.php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\DB;

class AuthController extends Controller {
    public function showLogin(): void {
        if (Auth::check()) {
            if (Auth::isSuperAdmin()) {
                $this->redirect('/admin/dashboard');
            } else {
                $concerns = Auth::getUserConcerns(Auth::id());
                if (count($concerns) > 1 && empty(Auth::concernId())) {
                    $this->redirect('/select-concern');
                } elseif (Auth::isShopConcern(Auth::concernId())) {
                    $this->redirect('/m/billing');
                } else {
                    $this->redirect('/dashboard');
                }
            }
        }

        $this->render('auth/login', [
            'pageTitle' => 'Sign In',
            'noLayout'  => true,
        ]);
    }

    public function login(): void {
        $this->validateCsrf();

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->flash('error', 'Please enter your email and password.');
            $this->redirect('/login');
        }

        if (Auth::login($email, $password)) {
            $user = Auth::user();

            if (Auth::isSuperAdmin()) {
                $this->flash('success', 'Welcome back, ' . $user['name'] . '!');
                $this->redirect('/admin/dashboard');
            }

            // Check how many concerns / shops this person is assigned to
            $userConcerns = Auth::getUserConcerns($user['id']);

            if (count($userConcerns) > 1) {
                // Multi-concern user (e.g. Abi assigned to Mangalam Unit, Sulur Shop, Mettupalayam Shop)
                // Prompt user to select which concern/shop to open
                $this->redirect('/select-concern');
            } elseif (count($userConcerns) === 1) {
                $cid = (int)$userConcerns[0]['id'];
                Auth::setSessionConcern($cid);
                $this->flash('success', 'Welcome back, ' . $user['name'] . '!');
                $this->redirect('/dashboard');
            } else {
                // Fallback for user without explicit user_concerns entry
                $cid = Auth::concernId();
                $this->flash('success', 'Welcome back, ' . $user['name'] . '!');
                $this->redirect('/dashboard');
            }
        } else {
            $this->flash('error', 'Invalid email or password, or your organization account is inactive.');
            $this->redirect('/login');
        }
    }

    /**
     * Concern / Shop Selection Screen (When user has access to 2 or more locations)
     */
    public function selectConcern(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        if (Auth::isSuperAdmin()) {
            $this->redirect('/admin/dashboard');
        }

        $userId = Auth::id();
        $concerns = Auth::getUserConcerns($userId);

        if (empty($concerns)) {
            $this->flash('error', 'No active concern or shop assigned to your account. Please contact an administrator.');
            Auth::logout();
            $this->redirect('/login');
        }

        // If only 1 concern and not explicitly switching
        if (count($concerns) === 1 && !isset($_GET['switch'])) {
            $c = $concerns[0];
            Auth::setSessionConcern((int)$c['id']);
            if (Auth::isShopConcern((int)$c['id'])) {
                $this->redirect('/m/billing');
            } else {
                $this->redirect('/dashboard');
            }
        }

        $this->render('auth/select_concern', [
            'pageTitle'  => 'Select Concern / Shop',
            'noLayout'   => true,
            'concerns'   => $concerns,
            'currentCid' => Auth::concernId(),
            'user'       => Auth::user(),
        ]);
    }

    /**
     * Process chosen concern and direct to appropriate landing page
     */
    public function setConcern(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $this->validateCsrf();
        $concernId = (int)($_POST['concern_id'] ?? 0);
        $userId = Auth::id();

        // Security: verify user actually belongs to this concern
        $allowedConcerns = Auth::getUserConcerns($userId);
        $allowedIds = array_column($allowedConcerns, 'id');

        if (!in_array($concernId, $allowedIds, true) && !Auth::isSuperAdmin()) {
            $this->flash('error', 'You do not have access to the selected business unit.');
            $this->redirect('/select-concern');
        }

        Auth::setSessionConcern($concernId);

        $chosenConcern = null;
        foreach ($allowedConcerns as $ac) {
            if ($ac['id'] == $concernId) {
                $chosenConcern = $ac;
                break;
            }
        }
        $name = $chosenConcern['name'] ?? 'Concern';

        $this->flash('success', "Active workspace set to {$name}.");
        $this->redirect('/dashboard');
    }

    /**
     * Switch concern from navbar dropdown or modal
     */
    public function switchConcernAction(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $concernId = (int)($_GET['id'] ?? 0);
        $userId = Auth::id();

        if (Auth::isSuperAdmin()) {
            Auth::switchConcern($concernId ?: null);
            $this->redirect($concernId ? '/dashboard' : '/admin/dashboard');
        }

        $allowedConcerns = Auth::getUserConcerns($userId);
        $allowedIds = array_column($allowedConcerns, 'id');

        if (!in_array($concernId, $allowedIds, true)) {
            $this->flash('error', 'You do not have access to that location.');
            $this->redirect('/dashboard');
        }

        Auth::setSessionConcern($concernId);

        $chosenConcern = null;
        foreach ($allowedConcerns as $ac) {
            if ($ac['id'] == $concernId) {
                $chosenConcern = $ac;
                break;
            }
        }
        $name = $chosenConcern['name'] ?? 'Concern';

        $this->flash('success', "Switched to {$name} successfully.");
        $this->redirect('/dashboard');
    }

    public function logout(): void {
        Auth::logout();
        $this->redirect('/login');
    }
}
