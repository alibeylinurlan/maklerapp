<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            // Xətai rayonu
            ['bina_id' => '314', 'slug' => 'ag-sheher', 'name_az' => 'Ağ Şəhər'],
            ['bina_id' => '100', 'slug' => 'ehmedli-qesebesi', 'name_az' => 'Əhmədli qəsəbəsi'],
            ['bina_id' => '99', 'slug' => 'hezi-aslanov-qesebesi', 'name_az' => 'Həzi Aslanov qəsəbəsi'],
            ['bina_id' => '200', 'slug' => 'kohne-gunesli', 'name_az' => 'Köhnə Günəşli'],
            ['bina_id' => '233', 'slug' => 'nzs', 'name_az' => 'NZS'],

            // Nəsimi rayonu
            ['bina_id' => '123', 'slug' => '1-ci-mikrorayon', 'name_az' => '1-ci mikrorayon'],
            ['bina_id' => '124', 'slug' => '2-ci-mikrorayon', 'name_az' => '2-ci mikrorayon'],
            ['bina_id' => '125', 'slug' => '3-cu-mikrorayon', 'name_az' => '3-cü mikrorayon'],
            ['bina_id' => '126', 'slug' => '4-cu-mikrorayon', 'name_az' => '4-cü mikrorayon'],
            ['bina_id' => '127', 'slug' => '5-ci-mikrorayon', 'name_az' => '5-ci mikrorayon'],
            ['bina_id' => '70', 'slug' => 'kubinka', 'name_az' => 'Kubinka'],

            // Nərimanov
            ['bina_id' => '142', 'slug' => 'boyuksor', 'name_az' => 'Böyükşor'],

            // Digər yerlər
            ['bina_id' => '246', 'slug' => 'ag-seher', 'name_az' => 'Ağ Şəhər (2)'],
            ['bina_id' => '31', 'slug' => 'ayna-sultanova-heykeli', 'name_az' => 'Ayna Sultanova heykəli'],
            ['bina_id' => '182', 'slug' => 'azadliq-meydani', 'name_az' => 'Azadlıq meydanı'],
            ['bina_id' => '174', 'slug' => 'azneft-meydani', 'name_az' => 'Azneft meydanı'],
            ['bina_id' => '164', 'slug' => 'baki-dovlet-universiteti', 'name_az' => 'Bakı Dövlət Universiteti'],
            ['bina_id' => '168', 'slug' => 'besmertebe', 'name_az' => 'Beşmərtəbə'],
            ['bina_id' => '170', 'slug' => 'dovlet-statistika-komitesi', 'name_az' => 'Dövlət Statistika Komitəsi'],
            ['bina_id' => '151', 'slug' => 'fontanlar-bagi', 'name_az' => 'Fəvvarələr bağı'],
            ['bina_id' => '363', 'slug' => 'grandhayatresidence', 'name_az' => 'Grand Hayat Residence'],
            ['bina_id' => '172', 'slug' => 'iceri-seher', 'name_az' => 'İçəri Şəhər'],
            ['bina_id' => '25', 'slug' => 'koala-parki', 'name_az' => 'Koala parkı'],
            ['bina_id' => '152', 'slug' => 'qis-parki', 'name_az' => 'Qış parkı'],
            ['bina_id' => '364', 'slug' => 'melissa-park', 'name_az' => 'Melissa Park'],
            ['bina_id' => '157', 'slug' => 'botanika-bagi', 'name_az' => 'Botanika bağı'],
            ['bina_id' => '361', 'slug' => 'merkezi-park', 'name_az' => 'Mərkəzi park'],
            ['bina_id' => '32', 'slug' => 'nerimanov-heykeli', 'name_az' => 'Nərimanov heykəli'],
            ['bina_id' => '133', 'slug' => 'park-zorge', 'name_az' => 'Park Zorge'],
            ['bina_id' => '269', 'slug' => 'port-baku', 'name_az' => 'Port Baku'],
            ['bina_id' => '177', 'slug' => 'rusiya-sefirliyi', 'name_az' => 'Rusiya səfirliyi'],
            ['bina_id' => '382', 'slug' => 'sea-breeze-event-hall', 'name_az' => 'Sea Breeze Event Hall'],
            ['bina_id' => '28', 'slug' => 'semed-vurgun-parki', 'name_az' => 'Səməd Vurğun parkı'],
            ['bina_id' => '183', 'slug' => 'sirk', 'name_az' => 'Sirk'],

            // Metrolar
            ['bina_id' => '5', 'slug' => '20-yanvar', 'name_az' => '20 Yanvar m.'],
            ['bina_id' => '8', 'slug' => '28-may', 'name_az' => '28 May m.'],
            ['bina_id' => '279', 'slug' => '8-noyabr', 'name_az' => '8 Noyabr m.'],
            ['bina_id' => '266', 'slug' => 'avtovagzal', 'name_az' => 'Avtovağzal m.'],
            ['bina_id' => '6', 'slug' => 'azadliq-prospekti', 'name_az' => 'Azadlıq prospekti m.'],
            ['bina_id' => '63', 'slug' => 'bakmil', 'name_az' => 'Bakmil m.'],
            ['bina_id' => '61', 'slug' => 'dernegul', 'name_az' => 'Dərnəgül m.'],
            ['bina_id' => '34', 'slug' => 'elmler-akademiyasi', 'name_az' => 'Elmlər Akademiyası m.'],
            ['bina_id' => '51', 'slug' => 'ehmedli', 'name_az' => 'Əhmədli m.'],
            ['bina_id' => '2', 'slug' => 'genclik', 'name_az' => 'Gənclik m.'],
            ['bina_id' => '33', 'slug' => 'hezi-aslanov', 'name_az' => 'Həzi Aslanov m.'],
            ['bina_id' => '54', 'slug' => 'xalqlar-dostlugu', 'name_az' => 'Xalqlar Dostluğu m.'],
            ['bina_id' => '37', 'slug' => 'iceri-seher-metrosu', 'name_az' => 'İçəri Şəhər m.'],
            ['bina_id' => '7', 'slug' => 'insaatcilar', 'name_az' => 'İnşaatçılar m.'],
            ['bina_id' => '4', 'slug' => 'koroglu', 'name_az' => 'Koroğlu m.'],
            ['bina_id' => '52', 'slug' => 'qara-qarayev', 'name_az' => 'Qara Qarayev m.'],
            ['bina_id' => '59', 'slug' => 'memar-ecemi', 'name_az' => 'Memar Əcəmi m.'],
            ['bina_id' => '53', 'slug' => 'neftciler', 'name_az' => 'Neftçilər m.'],
            ['bina_id' => '1', 'slug' => 'neriman-nerimanov', 'name_az' => 'Nəriman Nərimanov m.'],
            ['bina_id' => '60', 'slug' => 'nesimi-metrosu', 'name_az' => 'Nəsimi m.'],
            ['bina_id' => '35', 'slug' => 'nizami-metrosu', 'name_az' => 'Nizami m.'],
            ['bina_id' => '38', 'slug' => 'sahil', 'name_az' => 'Sahil m.'],
            ['bina_id' => '36', 'slug' => 'sah-ismayil-xetai', 'name_az' => 'Şah İsmayıl Xətai m.'],
        ];

        foreach ($locations as $loc) {
            Location::updateOrCreate(['bina_id' => $loc['bina_id']], $loc);
        }
    }
}
