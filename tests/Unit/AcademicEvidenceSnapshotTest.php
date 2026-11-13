<?php

namespace Tests\Unit;

use App\Support\AcademicEvidence;
use PHPUnit\Framework\TestCase;

final class AcademicEvidenceSnapshotTest extends TestCase
{
    public function test_sql_database_json_key_order_does_not_spuriously_reject_unchanged_evidence(): void
    {
        $fresh = ['course_id'=>10, 'course_revision'=>2, 'lesson_count'=>4, 'graded_homework_submissions'=>1];
        $mysql = ['graded_homework_submissions'=>1, 'course_revision'=>2, 'course_id'=>10, 'lesson_count'=>4];

        $this->assertTrue(AcademicEvidence::matchesSnapshot($fresh,$mysql));
    }

    public function test_evidence_values_types_and_keys_must_match_exactly(): void
    {
        $original = ['course_id'=>10,'lesson_count'=>4,'passed_practice_attempts'=>1];

        $this->assertFalse(AcademicEvidence::matchesSnapshot($original,[
            'course_id'=>10,'lesson_count'=>5,'passed_practice_attempts'=>1,
        ]));
        $this->assertFalse(AcademicEvidence::matchesSnapshot($original,[
            'course_id'=>'10','lesson_count'=>4,'passed_practice_attempts'=>1,
        ]));
        $this->assertFalse(AcademicEvidence::matchesSnapshot($original,[
            'course_id'=>10,'lesson_count'=>4,
        ]));
        $this->assertFalse(AcademicEvidence::matchesSnapshot($original,[
            'course_id'=>10,'lesson_count'=>4,'passed_practice_attempts'=>1,'extra'=>0,
        ]));
    }
}
