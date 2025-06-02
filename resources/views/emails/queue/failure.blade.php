@component('mail::message')
# Queue Failure Alert - {{ $environment }} Environment

**Alert Time:** {{ $timestamp }}

## Queue Status Summary
- **Environment:** {{ $environment }}
- **Application:** {{ $appName }}
- **Queue Driver:** {{ $queueData['driver'] }}
- **Failed Jobs:** {{ $queueData['failed_jobs'] }}
- **Current Queue Size:** {{ $queueData['queue_size'] }}

## Affected Queues
@foreach($queueData['queues'] as $queue)
- {{ $queue }}
@endforeach

## What To Do Next
1. Check the application logs for detailed error information
2. Review failed jobs in the database or Laravel Horizon dashboard (if available)
3. Fix the underlying issues causing job failures
4. Retry or delete failed jobs as appropriate

@component('mail::button', ['url' => config('app.url') . '/api/health/queue'])
View Queue Status
@endcomponent

This is an automated message from the {{ $appName }} system.

Thanks,<br>
{{ $appName }} Team
@endcomponent
