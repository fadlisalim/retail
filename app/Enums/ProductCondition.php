<?php

namespace App\Enums;

enum ProductCondition: string
{
    case New = 'new';
    case NewMinorDefect = 'new_minor_defect';
    case NewProjectSurplus = 'new_project_surplus';
    case OpenBox = 'open_box';
    case DisplayUnit = 'display_unit';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::NewMinorDefect => 'Baru - Minor Defect',
            self::NewProjectSurplus => 'Baru - Sisa Proyek',
            self::OpenBox => 'Open Box',
            self::DisplayUnit => 'Bekas Display',
            self::Used => 'Bekas Pakai',
        };
    }

    /** Conditions that require the customer to acknowledge condition before checkout. */
    public function requiresAcknowledgement(): bool
    {
        return $this !== self::New;
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
