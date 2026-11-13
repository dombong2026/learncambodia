<?php

namespace Database\Seeders;

use App\Models\CefrLevel;
use Illuminate\Database\Seeder;

class CefrLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = json_decode(<<<'JSON'
[
  {
    "code": "A1",
    "category": "Basic User",
    "name": "Beginner",
    "description": "Understand and use basic everyday phrases, introduce yourself, and ask simple questions.",
    "sort_order": 1
  },
  {
    "code": "A2",
    "category": "Basic User",
    "name": "Elementary",
    "description": "Handle simple, routine conversations about familiar topics like shopping, family, and local geography.",
    "sort_order": 2
  },
  {
    "code": "B1",
    "category": "Independent User",
    "name": "Intermediate",
    "description": "Deal with most travel situations, describe experiences, and give brief reasons for opinions.",
    "sort_order": 3
  },
  {
    "code": "B2",
    "category": "Independent User",
    "name": "Upper-Intermediate",
    "description": "Understand complex texts, interact with confidence, and write clear, detailed texts.",
    "sort_order": 4
  },
  {
    "code": "C1",
    "category": "Proficient User",
    "name": "Advanced",
    "description": "Express ideas fluently and spontaneously for academic, social, and professional purposes.",
    "sort_order": 5
  },
  {
    "code": "C2",
    "category": "Proficient User",
    "name": "Proficient / Mastery",
    "description": "Understand virtually everything heard or read with ease and summarize complex information naturally.",
    "sort_order": 6
  }
]
JSON, true, 512, JSON_THROW_ON_ERROR);

        foreach ($levels as $level) {
            CefrLevel::updateOrCreate(['code' => $level['code']], $level);
        }
    }
}
