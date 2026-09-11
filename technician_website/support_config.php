<?php
/**
 * SmartOps Source: technician_website/support_config.php
 * Purpose: Support request option configuration by hotel asset.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

declare(strict_types=1);


// =============================================================================
// SECTION: Technician Support Asset Key
// =============================================================================
function technician_support_asset_key(string $hotelAsset): string
{
    $value = strtolower($hotelAsset);
    if (str_contains($value, 'hvac')) return 'hvac';
    if (str_contains($value, 'plumb')) return 'plumbing';
    if (str_contains($value, 'elect')) return 'electrical';
    if (str_contains($value, 'fire')) return 'fire';
    if (str_contains($value, 'elevator') || str_contains($value, 'lift')) return 'elevator';
    return 'special';
}


// =============================================================================
// SECTION: Technician Support Config
// =============================================================================
function technician_support_config(): array
{
    return [
        'hvac' => [
            'parts' => ['Air Filter', 'Thermostat', 'Fan Motor', 'Compressor', 'Refrigerant Valve'],
            'senior_reasons' => ['Complex Diagnosis', 'Refrigerant Leak', 'Pressure Testing', 'High-voltage Work', 'Control System Fault'],
            'outsourcing_services' => [
                'HVAC Specialist',
                'Chiller Contractor',
                'Refrigeration Contractor',
                'Ventilation Specialist',
                'Building Automation System Vendor',
            ],
            'outsourcing_reasons' => [
                'Specialist Equipment Required',
                'Manufacturer or Warranty Work',
                'Certified Contractor Required',
                'Major System Overhaul',
                'Work Outside Internal Capability',
            ],
        ],
        'plumbing' => [
            'parts' => ['Pipe Fitting', 'Water Valve', 'Faucet Cartridge', 'Drain Trap', 'Pump Seal'],
            'senior_reasons' => ['Hidden Leak Diagnosis', 'Main Water Isolation', 'Pump System Repair', 'Major Pipework Repair', 'Water Pressure Investigation'],
            'outsourcing_services' => [
                'Plumbing Contractor',
                'Pipe Replacement Specialist',
                'Water Pump Contractor',
                'Drainage Specialist',
                'Water Treatment Contractor',
            ],
            'outsourcing_reasons' => [
                'Major Pipe Replacement',
                'Specialist Drainage Equipment Required',
                'Certified Water System Work',
                'External Pump Repair Required',
                'Work Outside Internal Capability',
            ],
        ],
        'electrical' => [
            'parts' => ['Circuit Breaker', 'Fuse', 'Power Socket', 'Contactor', 'LED Driver'],
            'senior_reasons' => ['High-voltage Work', 'Electrical Isolation Required', 'Main Distribution Board', 'Safety Risk', 'Complex Fault Diagnosis'],
            'outsourcing_services' => [
                'Licensed Electrician',
                'Switchboard Contractor',
                'Generator Specialist',
                'UPS Specialist',
                'Electrical Testing Contractor',
            ],
            'outsourcing_reasons' => [
                'Licensed Work Required',
                'Specialist Testing Required',
                'Major Switchboard Work',
                'Generator or UPS Servicing',
                'Work Outside Internal Capability',
            ],
        ],
        'fire' => [
            'parts' => ['Smoke Detector', 'Alarm Sounder', 'Sprinkler Head', 'Control Module', 'Fire Hose Valve'],
            'senior_reasons' => ['Life-safety System Check', 'Control Panel Diagnosis', 'System Isolation Required', 'Compliance Verification', 'Complex Alarm Fault'],
            'outsourcing_services' => [
                'Fire Protection Contractor',
                'Fire Alarm Vendor',
                'Sprinkler System Contractor',
                'Fire Extinguisher Service',
                'Fire Safety Inspection Company',
            ],
            'outsourcing_reasons' => [
                'Certification Required',
                'Regulatory Testing Required',
                'Specialist Commissioning Required',
                'Major Fire System Repair',
                'Work Outside Internal Capability',
            ],
        ],
        'elevator' => [
            'parts' => ['Door Sensor', 'Push Button', 'Relay', 'Guide Shoe', 'Emergency Battery'],
            'senior_reasons' => ['Entrapment or Safety Risk', 'Controller Diagnosis', 'Door System Adjustment', 'Mechanical Inspection', 'Complex Lift Fault'],
            'outsourcing_services' => [
                'Elevator Manufacturer',
                'Lift Maintenance Contractor',
                'Escalator Specialist',
                'Elevator Safety Inspector',
                'Lift Control System Vendor',
            ],
            'outsourcing_reasons' => [
                'Licensed Contractor Required',
                'Statutory Inspection Required',
                'Manufacturer Specialist Required',
                'Major Lift System Repair',
                'Work Outside Internal Capability',
            ],
        ],
        'special' => [
            'parts' => ['Door Hardware', 'Furniture Fitting', 'Wall Fixture', 'Sealant', 'Fastener Set'],
            'senior_reasons' => ['Complex Diagnosis', 'Safety Review', 'Specialist Advice', 'Manager Approval Required', 'Multi-trade Coordination'],
            'outsourcing_services' => [
                'Building Maintenance Contractor',
                'Civil Works Contractor',
                'Carpentry Contractor',
                'Painting Contractor',
                'General Contractor',
            ],
            'outsourcing_reasons' => [
                'Specialist Service Required',
                'Manufacturer Support Required',
                'Multi-trade Work Required',
                'Major Building Repair',
                'Work Outside Internal Capability',
            ],
        ],
    ];
}
