<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('homebot:close-month')->dailyAt('08:00');
