# Media Storage & Delivery Strategy — Bunny Stream + S3 Masters

**Date**: 2026-01-19
**Status**: Decided (pending implementation)
**Scope**: CSL-Media-Service, CSL-Certification-Rest-API, CSL-Certification (player)

## Decision

**Bunny Stream for delivery + Cloudflare R2 for masters** (Backblaze B2 as
documented fallback).

Managed video pipeline for transcoding and global CDN delivery; cheap
S3-compatible object storage as the canonical home for original uploads.

Rationale: this is both the cheapest setup and the safest. Video egress is
the dominant cost in an LMS — R2 meters it at $0 — and keeping masters in
our own bucket means no video vendor ever holds the only copy.

## Current State (what this replaces)

- Uploads land in MinIO `media-raw` bucket (S3 disk in the media service).
- `ProcessMedia` job runs ffmpeg → adaptive HLS (360p + 720p) → MinIO
  `media-processed` bucket.
- `MediaStreamingController` validates a JWT and proxies **every** manifest,
  playlist and segment through Laravel. Storage URLs are never exposed.
- `secure-video-player.tsx` plays via hls.js with an `AuthPropagatingLoader`
  that re-attaches the token to each sub-request.
- `BunnyStreamService` already exists in this API but is not the primary path.

Known ceiling: MinIO is single-VPS disk (no redundancy), and the PHP proxy
serves all video bytes — both limit scale regardless of provider choice.

## Target Architecture

```
Instructor upload
   │
   ▼
media-raw  (R2 — canonical master, S3-compatible)   ← keep forever
   │
   ▼
Bunny Stream  (pull or push transcode → adaptive HLS)    ← replaceable
   │
   ▼
Bunny CDN + token auth                                   ← delivery layer
   │
   ▼
hls.js player (unchanged — plain HLS + signed URLs)
```

| Layer | Decision | Replaceable? |
|---|---|---|
| Masters (raw uploads) | R2 bucket — S3 API | Yes: ~5-min provider swap |
| Transcoding | Bunny Stream | Yes: `ProcessMedia`/ffmpeg fallback exists |
| Delivery + auth | Bunny signed URLs / token auth | Yes: JWT layer stays ours |
| Player | hls.js, vendor-agnostic | Already portable |

## Download Protection — Unchanged Semantics

The "learners can't download" guarantee does **not** come from MinIO; it comes
from HLS segmentation + server-side token gating + no exposed MP4 URL. All of
that carries over: Bunny signed URLs play the role our JWT proxy plays today.

Honest framing (unchanged, and how we should describe it to clients):
deterrence, not DRM. A determined user can capture segments under any of these
setups. True DRM (Widevine/FairPlay) remains a v2 decision.

## Why Not the Alternatives

- **Cloudinary**: works (authenticated media type + signed delivery), but
  video egress is the expensive end of their credit model, and using it as
  master storage couples retention to the vendor — exactly what we're avoiding.
- **Cloud storage only (R2/B2 + keep ffmpeg + PHP proxy)**: valid minimal move
  (env-var change), but keeps the byte-proxy bottleneck and MinIO-style ops.
- **Pure MinIO status quo**: free but single-box, no redundancy, proxy ceiling.

## Migration Path

1. **Phase 0 — abstraction check**: confirm `FileStorage`/`ProcessMedia` can
   point `media-raw`/`media-processed` at an S3-compatible endpoint via env.
2. **Phase 1 — masters move**: provision R2 (or B2), repoint disks, sync
   existing MinIO objects (`rclone`/`aws s3 sync`). Code change ≈ zero.
3. **Phase 2 — delivery move**: upload lands in R2 `media-raw` first, then a
   job triggers Bunny fetch-ingest against a presigned R2 URL and stores the
   Bunny video id on the `MediaUpload` record. `MediaStreamingController`
   stops proxying bytes; playback tokens are issued by the Rest API.
4. **Phase 3 — retire**: drop ffmpeg queue/MinIO once parity is verified;
   keep `ProcessMedia` as the documented fallback transcode path.

## Exit / Vendor Risk Plan

- Masters always dual-reside in our bucket — never upload to Bunny without the
  R2/B2 write landing first.
- If Bunny is suspended or shutters: re-transcode masters with the existing
  ffmpeg pipeline, or stand the JWT proxy back up. Painful week, not data loss.
- Applies equally to price hikes, ToS enforcement, and acquisitions — not just
  bankruptcy.

## Cost Notes (order-of-magnitude, verify at implementation)

- R2: ~$15/TB/mo storage, $0 egress. B2: ~$6/TB/mo, egress free ≤3× storage.
- Bunny Stream: ~$10/TB storage + sub-cent/GB egress — vs Cloudinary where the
  same streamed hours cost materially more.
- Player/CDN bandwidth is where LMS spend lives; optimizing egress is the win.

## Resolved Implementation Choices

**Masters: Cloudflare R2.** Both satisfy the contract, but R2's zero egress
decides it — the worst case for this design is pulling every master back out
to re-transcode elsewhere, and that event is free on R2 while B2 bills it
beyond the 3×-storage allowance. B2 remains the documented fallback.

**Token issuing: Rest API via `BunnyStreamService`, not a media-service
façade.** The Rest API already owns playback authorization (enrollment,
entitlement checks) and already mints the JWTs the media service validates.
Adding Bunny-signed URL generation next to that is the smallest change;
`MediaStreamingController` is retained only as the fallback delivery path.

**Ingest: upload → R2, then Bunny fetch-ingest.** Never send a file to Bunny
before its master is durably in our bucket — the "no vendor holds the only
copy" guarantee is enforced by construction, not discipline. Flow: client
upload → media service writes `media-raw` on R2 → job calls Bunny fetch with
a presigned GET → Bunny transcodes and serves; master stays untouched in R2.
