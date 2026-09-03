<?php

namespace Database\Seeders;

use App\Models\EvaluationSection;
use Illuminate\Database\Seeder;

class EvaluationSectionSeeder extends Seeder
{
    /**
     * Default evaluation form, transcribed from the "Oia Evaluation Form" doc:
     * sections A-H share the Unsatisfactory(1)/Marginal(2)/Satisfactory(3)/
     * Highly Satisfactory(4)/Exceptional(5)/N/A rating scale (rendered as
     * labeled buttons on the form, mapped back to the label on the PDF); the
     * line-manager comments and action-plan blocks are free text. Fully
     * editable afterward via the admin screen at /settings/evaluations.
     * Idempotent (safe to re-run) — also removes the earlier generic
     * placeholder sections (Job Knowledge/Punctuality/Comments) this
     * superseded.
     */
    public function run(): void
    {
        EvaluationSection::whereIn('title', ['Job Knowledge', 'Punctuality', 'Comments'])->delete();

        $sections = [
            [
                'title' => 'A) Work Behavior',
                'question_type' => 'rating_1_5',
                'questions' => [
                    'Follows instructions',
                    'Proactive approach',
                    'Planning & organizing',
                    'Accepts constructive criticism',
                    'Flexible & adaptable',
                ],
            ],
            [
                'title' => 'B) Technical Skills',
                'question_type' => 'rating_1_5',
                'questions' => [
                    'Job Knowledge',
                    'Application of skills',
                    'Analyzing the problem',
                    'Follows proper procedures',
                    'Follows standards',
                    'Learning new skills',
                ],
            ],
            [
                'title' => 'C) Quality of Work',
                'question_type' => 'rating_1_5',
                'questions' => [
                    'Accuracy',
                    'Presentation',
                    'Reliability',
                    'Errorless work',
                    'Follow-through & Follow-up',
                ],
            ],
            [
                'title' => 'D) Handling Targets & Deadlines',
                'question_type' => 'rating_1_5',
                'questions' => [
                    'Completion of work on-time',
                    'Ability to work under pressure',
                    'Priority setting',
                ],
            ],
            [
                'title' => 'E) Interpersonal Skills',
                'question_type' => 'rating_1_5',
                'questions' => [
                    'Relationship with colleagues',
                    'Coordination & Cooperation',
                    'Teamwork',
                    'Problem-solving',
                    'Decision-making',
                ],
            ],
            [
                'title' => 'F) Communication Skills',
                'question_type' => 'rating_1_5',
                'questions' => [
                    'Oral & written expression',
                    'Speaking in English',
                    'Shares information & Knowledge',
                    'Reporting',
                ],
            ],
            [
                'title' => 'G) Willingness to Learn and Develop Skills',
                'question_type' => 'rating_1_5',
                'questions' => [
                    'Seeks training and development',
                    'Open to ideas',
                ],
            ],
            [
                'title' => 'H) Code of Conduct',
                'question_type' => 'rating_1_5',
                'questions' => [
                    'Office etiquette (Email & mobile phone etiquette)',
                    'Attendance',
                    'Punctuality',
                    'Dress code',
                    'Trustworthy & Confidentiality',
                    'Enthusiastic, Fair & mature',
                ],
            ],
            [
                'title' => 'Comments & Suggestions by the Line Manager',
                'question_type' => 'text',
                'questions' => [
                    'Comments & suggestions',
                ],
            ],
            [
                'title' => 'Action Plans for Development',
                'question_type' => 'text',
                'questions' => [
                    'Action plans for development',
                ],
            ],
        ];

        foreach ($sections as $sortOrder => $sectionData) {
            $section = EvaluationSection::updateOrCreate(
                ['title' => $sectionData['title']],
                [
                    'question_type' => $sectionData['question_type'],
                    'sort_order' => $sortOrder + 1,
                    'is_active' => true,
                ]
            );

            foreach ($sectionData['questions'] as $index => $questionText) {
                $section->questions()->updateOrCreate(
                    ['question_text' => $questionText],
                    ['sort_order' => $index + 1, 'is_active' => true]
                );
            }
        }
    }
}
