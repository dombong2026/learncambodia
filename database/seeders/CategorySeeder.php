<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(<<<'JSON'
[
  {
    "code": "GE",
    "name": "General English",
    "description": "Everyday speaking, listening, reading and writing, with learning pathways mapped to the learner's level.",
    "group": "english-courses",
    "sort_order": 1
  },
  {
    "code": "ESL",
    "name": "English as a Second Language",
    "description": "English learning for non-native speakers, often in communities where English is widely used.",
    "group": "english-courses",
    "sort_order": 2
  },
  {
    "code": "EFL",
    "name": "English as a Foreign Language",
    "description": "English learning for students in countries where English is not the main community language.",
    "group": "english-courses",
    "sort_order": 3
  },
  {
    "code": "BE",
    "name": "Business English",
    "description": "Practical English for meetings, workplace communication, presentations and international business.",
    "group": "english-courses",
    "sort_order": 4
  },
  {
    "code": "EAP",
    "name": "English for Academic Purposes",
    "description": "Academic reading, research communication, essay writing, seminars and university preparation.",
    "group": "specialist-english",
    "sort_order": 5
  },
  {
    "code": "ESP",
    "name": "English for Specific Purposes",
    "description": "Profession-focused English, including hospitality, health care, aviation and other fields.",
    "group": "specialist-english",
    "sort_order": 6
  },
  {
    "code": "TOEIC",
    "name": "Test of English for International Communication",
    "description": "Preparation pathway for a test of English skills commonly used in international workplaces.",
    "group": "exam-preparation",
    "sort_order": 7
  },
  {
    "code": "TOEFL",
    "name": "Test of English as a Foreign Language",
    "description": "Academic English preparation for the TOEFL family of assessments used by many institutions.",
    "group": "exam-preparation",
    "sort_order": 8
  },
  {
    "code": "IELTS",
    "name": "International English Language Testing System",
    "description": "English language proficiency preparation relevant to study, work and migration.",
    "group": "exam-preparation",
    "sort_order": 9
  },
  {
    "code": "PTE",
    "name": "Pearson Test of English",
    "description": "Preparation for a computer-based English proficiency assessment used for study and some visa pathways.",
    "group": "exam-preparation",
    "sort_order": 10
  },
  {
    "code": "DET",
    "name": "Duolingo English Test",
    "description": "Preparation for an online English proficiency test accepted by participating institutions.",
    "group": "exam-preparation",
    "sort_order": 11
  },
  {
    "code": "OET",
    "name": "Occupational English Test",
    "description": "Language test preparation for health care professionals.",
    "group": "exam-preparation",
    "sort_order": 12
  },
  {
    "code": "CELPIP",
    "name": "Canadian English Language Proficiency Index Program",
    "description": "Preparation focused on English communication assessed in certain Canadian immigration and citizenship processes.",
    "group": "exam-preparation",
    "sort_order": 13
  },
  {
    "code": "FCE",
    "name": "B2 First (formerly FCE)",
    "description": "Cambridge English qualification at approximately CEFR B2 level; preparation information, not official examination.",
    "group": "exam-preparation",
    "sort_order": 14
  },
  {
    "code": "CAE",
    "name": "C1 Advanced (formerly CAE)",
    "description": "Cambridge English qualification at approximately CEFR C1 level; preparation information, not official examination.",
    "group": "exam-preparation",
    "sort_order": 15
  },
  {
    "code": "CPE",
    "name": "C2 Proficiency (formerly CPE)",
    "description": "Cambridge English qualification at approximately CEFR C2 level; preparation information, not official examination.",
    "group": "exam-preparation",
    "sort_order": 16
  },
  {
    "code": "TEFL",
    "name": "Teaching English as a Foreign Language",
    "description": "Teacher-development pathway for teaching English in countries where it is not the primary language.",
    "group": "teacher-development",
    "sort_order": 17
  },
  {
    "code": "TESOL",
    "name": "Teaching English to Speakers of Other Languages",
    "description": "Broad teacher-development pathway for working with learners of English across contexts.",
    "group": "teacher-development",
    "sort_order": 18
  },
  {
    "code": "CELTA",
    "name": "Certificate in Teaching English to Speakers of Other Languages",
    "description": "Information and preparation for Cambridge English's practical initial teacher qualification; official awards require authorized centres.",
    "group": "teacher-development",
    "sort_order": 19
  },
  {
    "code": "DELTA",
    "name": "Diploma in Teaching English to Speakers of Other Languages",
    "description": "Advanced teacher-development pathway for experienced English teachers; official awards require authorized centres.",
    "group": "teacher-development",
    "sort_order": 20
  },
  {
    "code": "TKT",
    "name": "Teaching Knowledge Test",
    "description": "Preparation for Cambridge English's modular assessments of English-teaching knowledge.",
    "group": "teacher-development",
    "sort_order": 21
  },
  {
    "code": "TEYL",
    "name": "Teaching English to Young Learners",
    "description": "Teaching methodology focused on age-appropriate English instruction for children.",
    "group": "teacher-development",
    "sort_order": 22
  },
  {
    "code": "CEFR",
    "name": "Common European Framework of Reference for Languages",
    "description": "A six-level reference framework, A1 through C2, for describing language proficiency; it is not an examination.",
    "group": "frameworks",
    "sort_order": 23
  }
]
JSON, true, 512, JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $category = Category::firstOrNew(['code' => $row['code']]);
            $isNew = ! $category->exists;
            $category->fill([
                'slug' => Str::slug($row['code'].'-'.$row['name']),
                'name' => $row['name'],
                'group' => $row['group'],
                'description' => $row['description'],
                'sort_order' => $row['sort_order'],
            ]);

            // Never override an administrator's unpublished decision on re-seeding.
            if ($isNew) {
                $category->is_published = true;
            }

            $category->save();
        }
    }
}
