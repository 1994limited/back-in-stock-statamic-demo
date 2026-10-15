<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The demo's two accounts: Jo, who shops, and an admin for the Control
 * Panel. Both use the password "password", so this only runs locally.
 */
Artisan::command('demo:users', function () {
    if (! app()->environment('local')) {
        $this->error('demo:users only runs with APP_ENV=local: its passwords are public.');

        return 1;
    }

    $accounts = [
        ['jo@example.com', 'Jo Shopper', false],
        ['admin@example.com', 'Admin', true],
    ];

    foreach ($accounts as [$email, $name, $super]) {
        $user = \Statamic\Facades\User::findByEmail($email) ?? \Statamic\Facades\User::make()->email($email);
        $user->set('name', $name)->password('password');

        // makeSuper() takes no argument: it always makes a super user.
        $super ? $user->makeSuper() : $user->remove('super');

        $user->save();
        $this->info("{$email} / password".($super ? ' (Control Panel)' : ''));
    }
})->purpose('Create the demo shopper and admin accounts');

/*
 * People waiting for the sold-out books, so the Control Panel (Store → Back
 * in Stock) and the dashboard widget have something to show. Made directly,
 * already confirmed, without sending any email. Local only.
 */
Artisan::command('demo:requests {--fresh : Delete every request first}', function () {
    if (! app()->environment('local')) {
        $this->error('demo:requests only runs with APP_ENV=local.');

        return 1;
    }

    if ($this->option('fresh')) {
        \NineteenNinetyFour\BackInStock\Models\Item::query()->delete();
    }

    $wanted = [
        // [product id, variant, people waiting, unconfirmed]
        ['b00c0004-0000-4000-8000-000000000004', '', 14, 2], // The Lantern Ledger
        ['b00c0006-0000-4000-8000-000000000006', 'Hardback', 6, 1], // Coral Arithmetic, hardback
    ];

    $names = ['ada', 'bea', 'cal', 'dev', 'eli', 'fay', 'gus', 'hal', 'ivy', 'jon', 'kit', 'lou', 'max', 'nia', 'oto', 'pip', 'quin', 'ros'];

    foreach ($wanted as [$id, $variant, $waiting, $unconfirmed]) {
        $item = \NineteenNinetyFour\BackInStock\Models\Item::for($id, $variant);

        foreach (range(1, $waiting + $unconfirmed) as $i) {
            $email = $names[($i - 1) % count($names)].$i.'@example.com';
            $asked = now()->subDays(30 - $i)->subMinutes($i * 7);

            $alert = $item->alerts()->firstOrNew(['email' => $email]);
            $alert->forceFill([
                'product_id' => $id,
                'variant' => $variant,
                'site' => 'default',
                'locale' => 'en',
                'status' => $i <= $waiting ? 'waiting' : 'unconfirmed',
                'consent' => 'Email me once when it’s back in stock. Nothing else.',
                'confirmation_sent_at' => $asked,
                'confirmed_at' => $i <= $waiting ? $asked->copy()->addMinutes(3) : null,
                'created_at' => $asked,
            ])->save();
        }

        $this->info($item->product()?->name().": {$waiting} waiting, {$unconfirmed} unconfirmed");
    }
})->purpose('Add people waiting for the sold-out books (no emails sent)');
