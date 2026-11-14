<?php

/**
 * Thrown by a job that can never succeed (a malformed email address, say): the job is marked
 * failed straight away instead of being retried with back-off.
 */
class PermanentFailure extends Exception
{
}
