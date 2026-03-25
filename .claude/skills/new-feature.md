---
description: Implement a feature, validate existing tests, add new tests, and re-validate
user-invocable: true
---

# New Feature Workflow

Follow these steps in order. Do not skip any step.

## Step 1: Implement the feature

Implement the requested feature. Use the todo list to track progress if the task has multiple parts.

## Step 2: Run existing tests

Run `task test` to verify existing tests still pass. If any test fails, fix the issue before proceeding. Do not continue until all tests pass.

## Step 3: Write new tests

Write tests that cover the new feature. Place tests in the appropriate `_test.go` file alongside the code being tested. Follow the existing test patterns in the project (table-driven tests, `t.Run` subtests, etc).

## Step 4: Run all tests again

Run `task test` to verify both existing and new tests pass. If any test fails, fix it and re-run until all tests pass.
