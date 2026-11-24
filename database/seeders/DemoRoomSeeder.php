<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\User;
use App\Services\ConsumptionService;
use App\Services\PurchaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoRoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $owner = User::query()->updateOrCreate(
                ['email' => 'owner@example.com'],
                ['name' => 'وحید', 'password' => Hash::make('password')],
            );

            $room = Room::query()->updateOrCreate(
                ['owner_user_id' => $owner->id, 'name' => 'اتاق ما'],
                ['currency' => 'TOMAN', 'description' => 'اتاق نمونه دخل‌وخرج با خرید موجودی‌دار و خرج مشترک'],
            );

            $members = collect(['وحید', 'محمدرضا', 'امیر', 'مهدی', 'رعنا', 'علی', 'رضا', 'حسین', 'نیما'])
                ->mapWithKeys(function (string $name) use ($room, $owner): array {
                    $member = RoomMember::query()->updateOrCreate(
                        ['room_id' => $room->id, 'display_name' => $name],
                        [
                            'user_id' => $name === 'وحید' ? $owner->id : null,
                            'role' => $name === 'وحید' ? 'owner' : 'member',
                            'is_active' => true,
                        ],
                    );

                    return [$name => $member];
                });

            $purchaseService = app(PurchaseService::class);
            $consumptionService = app(ConsumptionService::class);
            $eggPurchase = $purchaseService->create($room, $owner, [
                'request_id' => '11111111-1111-4111-8111-111111111111',
                'payer_member_id' => $members['وحید']->id,
                'title' => 'تخم‌مرغ',
                'kind' => 'inventory',
                'amount_toman' => 500000,
                'unit_label' => 'عدد',
                'total_quantity_milliunits' => 30000,
                'purchased_at' => '2026-10-15 08:00:00',
                'note' => 'شانه ۳۰ عددی برای مثال محاسبه تجمعی',
            ]);

            foreach ([
                ['member' => 'محمدرضا', 'quantity' => 2000, 'request_id' => '22222222-2222-4222-8222-222222222222', 'time' => '2026-10-15 09:00:00'],
                ['member' => 'امیر', 'quantity' => 1000, 'request_id' => '33333333-3333-4333-8333-333333333333', 'time' => '2026-10-15 09:15:00'],
                ['member' => 'مهدی', 'quantity' => 2000, 'request_id' => '44444444-4444-4444-8444-444444444444', 'time' => '2026-10-15 09:30:00'],
            ] as $consumption) {
                $consumptionService->create($room, $eggPurchase, $owner, [
                    'request_id' => $consumption['request_id'],
                    'consumer_member_id' => $members[$consumption['member']]->id,
                    'quantity_milliunits' => $consumption['quantity'],
                    'consumed_at' => $consumption['time'],
                ]);
            }

            $purchaseService->create($room, $owner, [
                'request_id' => '55555555-5555-4555-8555-555555555555',
                'payer_member_id' => $members['مهدی']->id,
                'title' => 'پرتقال',
                'kind' => 'shared',
                'amount_toman' => 30000,
                'unit_label' => 'کیلوگرم',
                'total_quantity_milliunits' => 1000,
                'share_mode' => 'equal',
                'participants' => [$members['محمدرضا']->id, $members['امیر']->id, $members['رعنا']->id],
                'purchased_at' => '2026-10-15 10:00:00',
                'note' => 'سهم هر نفر ۱۰٬۰۰۰ تومان',
            ]);

            $purchaseService->create($room, $owner, [
                'request_id' => '66666666-6666-4666-8666-666666666666',
                'payer_member_id' => $members['مهدی']->id,
                'title' => 'خوراکی مشترک وحید و دو نفر دیگر',
                'kind' => 'shared',
                'amount_toman' => 30000,
                'unit_label' => 'بسته',
                'share_mode' => 'equal',
                'participants' => [$members['وحید']->id, $members['حسین']->id, $members['نیما']->id],
                'purchased_at' => '2026-10-15 10:30:00',
                'note' => 'خرج مستقل برای نمایش تهاتر دوطرفه',
            ]);
        });
    }
}
