<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Fresh accounts go straight to the verification notice. Routing them
     * through the account area instead would hit the verified middleware,
     * whose guest redirect overwrites the intended URL and loses the page
     * that started the sign-up, such as an event's attendance action.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
