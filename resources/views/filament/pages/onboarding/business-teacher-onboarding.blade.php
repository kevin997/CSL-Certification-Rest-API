<x-filament-panels::page>
    <x-filament-panels::form wire:submit="onboard">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="[
                \Filament\Actions\Action::make('onboard')
                    ->label('Onboard Business Teacher')
                    ->submit('onboard')
                    ->color('primary'),
            ]"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
