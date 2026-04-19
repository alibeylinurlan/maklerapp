<?php

use Illuminate\Support\Facades\Schedule;

// "Silindi" statusunda 1 saatdan artıq olan uyğunluqları hər 15 dəqiqədə bir sil
Schedule::command('matches:purge-dismissed')->everyFifteenMinutes();
