# Family Office — Product Specification

## Overview

**Project Name:** `familyoffice.brianrosenthal.org` (it will live there for now and primarily serve my family)

### Mission

Family Office is a collaborative household management application whose primary purpose is ensuring that important recurring responsibilities are never forgotten.

Unlike a traditional task manager, Family Office is centered around **long-term recurring obligations** rather than one-time tasks. It serves as the family's institutional memory, keeping track of obligations, assets, important documents, insurance policies, maintenance schedules, and trusted contacts.

The application is designed for families rather than professional financial offices.

# Core Design Philosophy

The application revolves around one central concept:

> **Recurring Obligations**

Everything else (assets, documents, insurance policies, vendors, family members, etc.) exists primarily to support these obligations.

Examples:

- Renew passport
- Pay property taxes
- Replace HVAC filter
- Annual physical
- Quarterly investment review
- Renew umbrella insurance
- Clean gutters

Every recurring obligation answers four questions:

- What needs to happen?
- When is it due?
- Who is responsible?
- What information is needed to complete it?


# Navigation

## Main menu (like the left menu)

1. Recurring Obligations
2. Household Assets
3. Document Vault
4. Contacts
5. Insurance Policies

## User Menu

- My Profile
- My Family / My Families
    - Family Settings
    - Users

# Module Specifications

# 1. Recurring obligations (like: Pay property taxes, Pay estimated taxes, Renew passports, Driver's license renewal, Car registration, Annual physicals, Dental cleanings, Replace HVAC filters, Smoke detector batteries, Life insurance premium, Umbrella insurance renewal, Safe deposit box visit, Update estate planning documents, Quarterly investment review)

## Fields

### Basic Information

- Title
- Description / Instructions
- Category
    - Financial
    - Insurance
    - Health
    - Home Maintenance
    - Vehicle
    - Legal
    - Personal
    - Other

### Scheduling

Support the following recurrence types:

- Every N days
- Every N weeks
- Every N months
- Every N years
- Specific day each month
- Specific date each year
- N days/weeks/months after last completion

Each obligation stores:

- Last completed date
- Next due date

### Ownership

- Responsible person
- Applies to
    - Entire family
    - Individual family member

### Status

- Active
- Inactive

### Linked Objects

Household Assets
Insurance Policy
Document
Vendor

## Completion History

Every completion creates a permanent history record.

History includes:

- Completion date
- Completed by
- Optional notes

History is never overwritten.


# 2. Household Assets

Represents physical assets owned by the family.

Examples:

- House
- Roof
- Boiler
- HVAC
- Water heater
- Vehicles
- Jewelry
- Appliances

## Fields

- Name
- Category
- Description
- Purchase date
- Purchase price (optional)
- Warranty information
- Photos
- Receipts — proof of purchase kept for tax records. Each receipt has a title, a
  description, and an image (JPEG/PNG/WebP/GIF/HEIC or PDF). Unlike asset photos,
  receipt images are stored as private files and are served only through a
  login-checked endpoint, never the public disk cache.

# 3. Document Vault

Secure storage for important family documents.

Examples

- Wills
- Trusts
- Insurance policies
- Deeds
- Passports
- Birth certificates
- Marriage certificates
- Tax returns
- Vaccination records
- Vehicle titles

Documents should be stored in the database, not on the file system.

## Fields

- Title
- Category
- Description
- Upload date
- Owner
- File attachment


# 4. Contacts
# Contacts / Directory

The application should have a single Contacts / Directory section rather than separate Directory and Vendor sections.

Contacts may include both people and organizations.

Examples:

- Family members
- Doctors
- Dentists
- Attorneys
- Accountants
- Financial advisors
- Insurance agents
- Contractors
- Plumbers
- Electricians
- HVAC companies
- Gardeners
- Schools
- Emergency contacts
- Trusted neighbors

## Fields

Each contact should include:

- Name
- Contact type
    - Person
    - Organization
- Categories / Roles
    - Family Member
    - Doctor
    - Dentist
    - Attorney
    - Accountant
    - Financial Advisor
    - Insurance Agent
    - Contractor
    - Plumber
    - Electrician
    - HVAC
    - Gardener
    - School
    - Emergency Contact
    - Other
- Organization / Company name
- Job title / Role description
- Phone number
- Email address
- Website
- Address
- Notes

## Relationships

Contacts should be linkable to other records in the system.

Examples:

- A family member can be linked to a health obligation.
- A doctor can be linked to an annual physical reminder.
- A dentist can be linked to a dental cleaning reminder.
- An attorney can be linked to estate planning documents.
- An insurance agent can be linked to an insurance policy.
- A plumber can be linked to a household asset or maintenance task.
- A school can be linked to a child or family member.

## Design Notes

Contacts should be useful as a standalone directory, not merely as supporting data for reminders.

A single contact may have multiple categories or roles.

For example:

- A person may be both a trusted neighbor and an emergency contact.
- A company may be both an HVAC provider and a plumber.
- A doctor may be both a physician and a family contact.

The UI should allow filtering contacts by category so the user can quickly view groups such as:

- Family
- Doctors
- Legal / Financial
- Insurance
- Home Services
- Emergency

# 5. Insurance Policies

Tracks all family insurance coverage.

Examples

- Homeowners
- Umbrella
- Auto
- Life
- Disability

## Fields

- Policy name
- Category
- Insurance company
- Policy number
- Effective date
- Expiration date
- Notes
