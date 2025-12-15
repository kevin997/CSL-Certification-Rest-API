@component('mail::message')
# Archival Summary Report

**Period:** Last 7 days
**Generated:** {{ now()->format('F j, Y H:i') }}

## Archival

**Jobs:** {{ $stats['total_jobs'] ?? 0 }}
**Successful:** {{ $stats['successful_jobs'] ?? 0 }}
**Failed:** {{ $stats['failed_jobs'] ?? 0 }}

## Storage

**Total Archives:** {{ $storageStats->total_archives ?? 0 }}
**Archived Messages:** {{ $storageStats->total_archived_messages ?? 0 }}
**Storage Used (MB):** {{ isset($storageStats->total_storage_mb) ? round($storageStats->total_storage_mb, 2) : 0 }}

## Search Index

**Indexed Messages:** {{ $searchStats['total_indexed'] ?? 0 }}

@endcomponent