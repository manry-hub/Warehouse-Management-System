<?php

namespace App\Filament\Resources\SiloResource\Pages;

use App\Filament\Resources\SiloResource;
use App\Models\SiloTelemetryReading;
use App\Services\SiloTelemetryService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSilo extends ViewRecord
{
    protected static string $resource = SiloResource::class;

    protected static string $view = 'filament.resources.silo-resource.pages.view-silo';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('simulate_reading')
                ->label('Ingest Telemetry Reading')
                ->icon('heroicon-o-signal')
                ->color('warning')
                ->form([
                    Forms\Components\TextInput::make('distance_m')
                        ->label('Sensor Ullage Distance (Meters)')
                        ->helperText('Distance measured from top radar transmitter to polymer surface (0 to '.$this->record->total_height_m.' m)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue($this->record->total_height_m)
                        ->required()
                        ->default($this->record->current_distance_m ?? 4.5),
                    Forms\Components\Select::make('sensor_type')
                        ->label('Sensor Type')
                        ->options([
                            SiloTelemetryReading::SENSOR_RADAR_80GHZ => '80 GHz Continuous Wave Radar (VEGAPULS / Sitrans)',
                            SiloTelemetryReading::SENSOR_ULTRASONIC => 'Ultrasonic Transceiver (EchoSmart)',
                            SiloTelemetryReading::SENSOR_PRESSURE => 'Differential Pressure / Hydrostatic',
                        ])
                        ->default(SiloTelemetryReading::SENSOR_RADAR_80GHZ)
                        ->required(),
                    Forms\Components\TextInput::make('temperature_c')
                        ->label('Silo Ambient Temperature (°C)')
                        ->numeric()
                        ->default(26.5),
                ])
                ->action(function (array $data, SiloTelemetryService $telemetryService): void {
                    $result = $telemetryService->ingestReading(
                        silo: $this->record,
                        distanceMeters: (float) $data['distance_m'],
                        sensorType: $data['sensor_type'],
                        temperatureC: isset($data['temperature_c']) ? (float) $data['temperature_c'] : null,
                        batteryPercentage: 99.0,
                        rawPayload: ['source' => 'filament_ui']
                    );

                    $this->record->refresh();

                    $metrics = $result['metrics'];
                    $msg = "Telemetry ingested: {$metrics['weight_kg']} kg ({$metrics['fill_percentage']}%) - Status: {$metrics['status']}";

                    if ($result['reorder_triggered']) {
                        $msg .= " | Auto-Reorder Triggered: {$result['purchase_requisition']->pr_number}";
                    }

                    Notification::make()
                        ->title('Silo Telemetry Ingested')
                        ->body($msg)
                        ->color($this->record->getStatusColor())
                        ->send();
                }),
            Actions\EditAction::make(),
        ];
    }
}
