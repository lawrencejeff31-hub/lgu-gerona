<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Purchase Request', 'code' => 'PR', 'prefix' => 'PR', 'requires_approval' => true,
                'schema' => [
                    'fields' => [
                        ['key' => 'pr_number', 'type' => 'string', 'required' => true, 'maxLength' => 50],
                        ['key' => 'fund_source', 'type' => 'string', 'required' => false, 'maxLength' => 255],
                        ['key' => 'estimated_cost', 'type' => 'number', 'required' => true, 'min' => 0]
                    ]
                ]
            ],
            [
                'name' => 'Obligation Request', 'code' => 'OBR', 'prefix' => 'OBR', 'requires_approval' => true,
                'schema' => [
                    'fields' => [
                        ['key' => 'obr_number', 'type' => 'string', 'required' => true, 'maxLength' => 50],
                        ['key' => 'amount', 'type' => 'number', 'required' => true, 'min' => 0],
                        ['key' => 'payee', 'type' => 'string', 'required' => true, 'maxLength' => 255]
                    ]
                ]
            ],
            [
                'name' => 'Purchase Order', 'code' => 'PO', 'prefix' => 'PO', 'requires_approval' => true,
                'schema' => [
                    'fields' => [
                        ['key' => 'po_number', 'type' => 'string', 'required' => true, 'maxLength' => 50],
                        ['key' => 'supplier', 'type' => 'string', 'required' => true, 'maxLength' => 255],
                        ['key' => 'po_date', 'type' => 'date', 'required' => true]
                    ]
                ]
            ],
            [
                'name' => 'Disbursement Voucher', 'code' => 'DV', 'prefix' => 'DV', 'requires_approval' => true,
                'schema' => [
                    'fields' => [
                        ['key' => 'dv_number', 'type' => 'string', 'required' => true, 'maxLength' => 50],
                        ['key' => 'amount', 'type' => 'number', 'required' => true, 'min' => 0],
                        ['key' => 'payee', 'type' => 'string', 'required' => true, 'maxLength' => 255]
                    ]
                ]
            ],
            [
                'name' => 'Invitation to Bid', 'code' => 'ITB', 'prefix' => 'ITB', 'requires_approval' => true,
                'schema' => [
                    'fields' => [
                        ['key' => 'project_name', 'type' => 'string', 'required' => true, 'maxLength' => 255],
                        ['key' => 'bid_opening_date', 'type' => 'date', 'required' => true]
                    ]
                ]
            ],
            [
                'name' => 'Abstract of Bids', 'code' => 'AB', 'prefix' => 'AB', 'requires_approval' => true,
                'schema' => [
                    'fields' => [
                        ['key' => 'project_name', 'type' => 'string', 'required' => true, 'maxLength' => 255],
                        ['key' => 'bid_amounts', 'type' => 'string', 'required' => false]
                    ]
                ]
            ],
            [
                'name' => 'Notice of Award', 'code' => 'NOA', 'prefix' => 'NOA', 'requires_approval' => true,
                'schema' => [
                    'fields' => [
                        ['key' => 'awardee', 'type' => 'string', 'required' => true, 'maxLength' => 255],
                        ['key' => 'award_date', 'type' => 'date', 'required' => true]
                    ]
                ]
            ],
            [
                'name' => 'Notice to Proceed', 'code' => 'NTP', 'prefix' => 'NTP', 'requires_approval' => true,
                'schema' => [
                    'fields' => [
                        ['key' => 'contractor', 'type' => 'string', 'required' => true, 'maxLength' => 255],
                        ['key' => 'start_date', 'type' => 'date', 'required' => true]
                    ]
                ]
            ],
            [
                'name' => 'Contract', 'code' => 'CONTRACT', 'prefix' => 'CON', 'requires_approval' => true,
                'schema' => [
                    'fields' => [
                        ['key' => 'contract_number', 'type' => 'string', 'required' => true, 'maxLength' => 50],
                        ['key' => 'start_date', 'type' => 'date', 'required' => true],
                        ['key' => 'end_date', 'type' => 'date', 'required' => true]
                    ]
                ]
            ],
            [
                'name' => 'Memorandum', 'code' => 'MEMO', 'prefix' => 'MEMO', 'requires_approval' => false,
                'schema' => [
                    'fields' => [
                        ['key' => 'memo_number', 'type' => 'string', 'required' => false, 'maxLength' => 50]
                    ]
                ]
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}