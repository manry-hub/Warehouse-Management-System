<?php

namespace App\Filament\Pages;

use App\Services\LotGenealogyService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class LotGenealogyPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Quality & Traceability';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'IATF 16949 Lot Genealogy & Recall';

    protected static ?string $slug = 'lot-genealogy';

    protected static string $view = 'filament.pages.lot-genealogy';

    public string $searchQuery = '';

    public string $direction = 'BACKWARD'; // BACKWARD or FORWARD

    public ?array $traceResult = null;

    public string $quarantineReason = '';

    public string $quarantineSeverity = 'MAJOR';

    public bool $showQuarantineModal = false;

    public function runTrace(): void
    {
        $query = trim($this->searchQuery);
        if (empty($query)) {
            Notification::make()
                ->title('Missing Query')
                ->body('Please enter a carton barcode, finished good lot number, raw resin lot, or DO number.')
                ->warning()
                ->send();

            return;
        }

        $service = app(LotGenealogyService::class);

        try {
            if ($this->direction === 'BACKWARD') {
                $this->traceResult = $service->traceBackward($query);
            } else {
                $this->traceResult = $service->traceForward($query);
            }

            Notification::make()
                ->title('Traceability Resolved')
                ->body("Successfully generated {$this->direction} genealogy graph for [{$query}].")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->traceResult = null;
            Notification::make()
                ->title('Traceability Search Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openQuarantineModal(): void
    {
        $this->showQuarantineModal = true;
    }

    public function closeQuarantineModal(): void
    {
        $this->showQuarantineModal = false;
        $this->quarantineReason = '';
    }

    public function executeQuarantine(): void
    {
        if (empty(trim($this->quarantineReason))) {
            Notification::make()
                ->title('Quarantine Reason Required')
                ->body('Please specify the quality audit or recall root cause.')
                ->warning()
                ->send();

            return;
        }

        $service = app(LotGenealogyService::class);

        try {
            $quarantine = $service->quarantineImpactedStock(
                identifier: $this->searchQuery,
                reason: $this->quarantineReason,
                userId: Auth::id(),
                severity: $this->quarantineSeverity
            );

            $this->closeQuarantineModal();
            $this->runTrace(); // Refresh graph to reflect new QUARANTINE statuses

            Notification::make()
                ->title('Quarantine Protocol Executed')
                ->body("Quarantine {$quarantine->quarantine_code} activated: {$quarantine->impacted_lots_count} lots isolated across {$quarantine->impacted_total_quantity} units.")
                ->danger()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Quarantine Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
