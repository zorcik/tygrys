<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;
use RuntimeException;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = base_path('ksiazki.txt');

        if (! file_exists($filePath)) {
            throw new RuntimeException("Plik {$filePath} nie istnieje.");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $knownAuthors = [
            'Bohdan Arct',
            'F. L. Justin',
            'Wacław Król',
            'Zbigniew Flisowski',
            'Józef Barski',
            'B. Kassner',
            'Krzysztof Kulicz i Adam Witold Wysocki',
            'Adam Zarzycki',
            'J. Prestone',
            'Stanisław Komornicki „Nałęcz”',
            'Jan Grabiński',
            'Ryszard Pietrzak',
            'P. Willam',
            'P. William',
            'Marek Sadzewicz',
            'S.T. Kald',
            'Andrzej Makowski',
            'Zbigniew Damski',
            'Adam Witold Wysocki',
            'Czesław Stempniewicz',
            'Witold Supiński',
            'Rajmund Szubański',
            'Adam Wysocki',
            'J.M. Woytt',
            'Włodzimierz Jaroszyński',
            'Stanisław Muranowski',
            'H. Hoffer',
            'Ryszard Leliwa',
            'Zdzisław Boczkowski',
            'Marek Dunin-Wąsowicz',
            'Gustaw Jarl',
            'Marek Koreywo',
            'Stanisław Biskupski',
            'Andrzej Kuczera',
            'A. Kuczera',
            'Iwona Kienzler',
            'Andrzej Stuglik',
            'Wacław Malten',
            'Andrzej Fryszkiewicz',
            'Władysław Kisielewski',
            'Roman Runek',
            'Andrzej R. Jadczak',
            'W. Dąbrowski',
            'Mieczysław Kieta',
            'Robert Weiss',
            'Janusz Wolniewicz',
            'Edward Obertyński',
            'D.J. Hope',
            'S.C. Sering',
            'Ireneusz Łapiński',
            'Zbigniew Wilamowski',
            'S.B. Bicker',
            'S. Winefield',
            'Wolfgang Leonhard',
            'Lech Niekrasz',
            'R. Jegorow, S Krakowski',
            'J. Steward',
            'Jacek Pałkiewicz',
            'Włodzimierz Kowalski',
            'Andrzej Robert Janczak',
            'Witold Kurpis',
            'Henryk Kawka',
            'S. Sosna-Sarno C. Tomczyk',
            'Sławomir Klimkiewicz',
            'T. Salter',
            'Janusz Figura Andrzej Wesołowski',
            'T. Stępień, W. Brodziński',
            'A. Kozak',
            'S. Klimkiewicz',
            'T. Dytko',
            'R. Szmigielski',
            'Z. Damski',
            'B. Kwiatkowski',
            'T. Bryła',
            'K. Ner',
            'A. Gierczak',
            'W.R. Zielrecki',
            'M. Golik',
            'J. Malczewski',
            'P.R. Frankowski',
            'A. Syta, Z. Hardt',
            'R. Pietrzak',
            'B. Gaczkowski',
            'Ł. Szwed',
            'A. Mozołowski',
            'S. Galicki',
            'T. Rawski',
            'R. Brzeski',
            'H. Dyjeta',
            'S. Ozimek',
            'R. Szubański',
            'Z. Kozakiewicz',
            'A. Monastyrski',
            'J. Marciniak',
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $details = null;
            $cleanLine = $line;

            if (preg_match('/(\(wyd\..*|\[\d+\].*|\[potrzebny przypis\].*)/u', $line, $matches)) {
                $details = trim($matches[1]);
                $cleanLine = trim(substr($line, 0, -strlen($matches[0])));
            }

            $matchedAuthor = null;
            foreach ($knownAuthors as $authorName) {
                if (str_contains($cleanLine, $authorName)) {
                    $matchedAuthor = $authorName;
                    break;
                }
            }

            if ($matchedAuthor) {
                $author = $matchedAuthor;
                $pos = strpos($cleanLine, $matchedAuthor);
                $title = trim(substr($cleanLine, 0, $pos));
                $title = rtrim($title, " \t\n\r\0\x0B-–—");
            } else {
                // Fallback in case of unexpected format
                $parts = preg_split('/[\-\–\—]/u', $cleanLine, 2);
                $title = trim($parts[0]);
                $author = isset($parts[1]) ? trim($parts[1]) : 'Nieznany';
            }

            Book::firstOrCreate(
                [
                    'title' => $title,
                    'author' => $author,
                ],
                [
                    'details' => $details,
                    'is_purchased' => false,
                ]
            );
        }
    }
}
