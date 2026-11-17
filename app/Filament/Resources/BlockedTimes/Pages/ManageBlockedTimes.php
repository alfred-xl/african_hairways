<?php

namespace App\Filament\Resources\BlockedTimes\Pages;

use App\Filament\Resources\BlockedTimes\BlockedTimeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBlockedTimes extends ManageRecords
{
    protected static string $resource = BlockedTimeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Block out time')
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by_user_id' => auth()->id()]),
        ];
    }
}
