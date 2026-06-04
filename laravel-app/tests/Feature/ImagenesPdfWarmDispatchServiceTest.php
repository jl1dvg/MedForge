<?php

namespace Tests\Feature;

use App\Jobs\GenerateImagenesPdfCacheJob;
use App\Modules\Reporting\Services\ImagenesPdfWarmDispatchService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImagenesPdfWarmDispatchServiceTest extends TestCase
{
    public function test_it_dispatches_pdf_warm_jobs_for_exam_case(): void
    {
        Queue::fake();

        $queued = (new ImagenesPdfWarmDispatchService())->dispatchForExamCase('281556', '0908931660');

        $this->assertSame(2, $queued);
        Queue::assertPushed(GenerateImagenesPdfCacheJob::class, 2);
        Queue::assertPushed(GenerateImagenesPdfCacheJob::class, function (GenerateImagenesPdfCacheJob $job): bool {
            return $job->type === 'imagenes-012b'
                && $job->params['form_id'] === '281556'
                && $job->params['hc_number'] === '0908931660'
                && $job->formId === '281556'
                && $job->queue === 'pdfs';
        });
        Queue::assertPushed(GenerateImagenesPdfCacheJob::class, function (GenerateImagenesPdfCacheJob $job): bool {
            return $job->type === 'imagenes-012a-cobertura'
                && $job->params['form_id'] === '281556'
                && $job->params['hc_number'] === '0908931660'
                && $job->formId === '281556'
                && $job->queue === 'pdfs';
        });
    }

    public function test_it_does_not_dispatch_when_identifiers_are_missing(): void
    {
        Queue::fake();

        $queued = (new ImagenesPdfWarmDispatchService())->dispatchForExamCase('', '0908931660');

        $this->assertSame(0, $queued);
        Queue::assertNothingPushed();
    }
}
