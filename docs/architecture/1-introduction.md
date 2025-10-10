# 1. Introduction

## Project Overview

CSL (Certification Platform) is a comprehensive **brownfield** learning management and certification system consisting of five interconnected projects. This architecture document serves as the single source of truth for the entire ecosystem, capturing the current state and ongoing enhancements.

## Brownfield Project Status

This is an **existing production system** with:
- ✅ 5 active codebases in production
- ✅ Multi-tenant architecture serving multiple environments
- ✅ Existing payment processing with 4 gateway integrations
- ✅ Course delivery, certification, and e-commerce functionality
- 🔄 Ongoing enhancement: Payment Gateway Centralization (EPIC-PGC-001)

## Active Enhancement: Payment Gateway Centralization

**Epic ID:** EPIC-PGC-001
**Duration:** 8 weeks
**Status:** Planning

This epic introduces an **optional opt-in system** for environments to use centralized payment processing through Environment 1's gateways, while maintaining backward compatibility with environment-specific gateways. Includes instructor commission tracking and withdrawal management.

---
