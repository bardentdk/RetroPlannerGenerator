<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Planning de Formation</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #000; }
        
        .header-box { 
            text-align: center; 
            margin-bottom: 20px; 
            border: 2px solid #000; 
            padding: 10px; 
            background-color: #f0f0f0;
        }
        .header-title { font-size: 18px; font-weight: bold; text-transform: uppercase; }
        .sub-title { font-size: 14px; margin-top: 5px; font-style: italic; }
        
        .student-name { 
            font-size: 16px; 
            font-weight: bold; 
            margin-bottom: 15px; 
            color: #be123c; /* Rouge Brand */
        }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        
        th { 
            background-color: #d1d5db; /* Gris clair comme Excel */
            border: 1px solid #000; 
            padding: 8px; 
            font-weight: bold; 
            text-align: center;
            vertical-align: middle;
        }

        td { 
            border: 1px solid #000; 
            padding: 8px; 
            text-align: center; 
            vertical-align: middle;
        }

        /* Colonnes spécifiques */
        .col-date { width: 15%; font-weight: bold; text-align: left; }
        .col-time { width: 25%; font-size: 11px; }
        .col-sign { width: 10%; }
        .col-total { width: 10%; font-weight: bold; background-color: #f3f4f6; }

        .signed { color: #059669; font-weight: bold; font-size: 16px; } /* V vert */
        .missing { color: #dc2626; font-weight: bold; font-size: 10px; } /* Rouge */

        .footer { 
            position: fixed; bottom: 0; left: 0; right: 0; 
            font-size: 10px; text-align: center; color: #666; 
            border-top: 1px solid #ccc; padding-top: 5px;
        }
    </style>
</head>
<body>

    <div class="header-box">
        <div class="header-title">PLANNING DE FORMATION</div>
        <div class="sub-title">{{ $trainingName }}</div>
    </div>

    <div class="student-name">
        Apprenant : {{ $studentName }}
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">DATE</th>
                <th colspan="2">MATIN (08:30 - 12:00)</th>
                <th colspan="2">APRES-MIDI (13:00 - 16:30)</th>
                <th rowspan="2">TOTAL<br>HEURES</th>
            </tr>
            <tr>
                <th>Module / Activité</th>
                <th>Emargement</th>
                <th>Module / Activité</th>
                <th>Emargement</th>
            </tr>
        </thead>
        <tbody>
            @foreach($days as $date => $slots)
                @php
                    $morning = $slots->firstWhere('period', 'morning');
                    $afternoon = $slots->firstWhere('period', 'afternoon');
                    
                    // Calcul des heures (3.5h par demi-journée)
                    $totalDay = 0;
                    if($morning && $morning->is_present) $totalDay += 3.5;
                    if($afternoon && $afternoon->is_present) $totalDay += 3.5;

                    // Formatage date
                    $displayDate = \Carbon\Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM YYYY');
                @endphp
                <tr>
                    <td class="col-date">{{ ucfirst($displayDate) }}</td>

                    <td class="col-time">
                        {{-- On laisse vide pour l'instant ou on met un texte par défaut --}}
                        <i>Formation</i>
                    </td>
                    <td class="col-sign">
                        @if($morning && $morning->is_present)
                            <span class="signed">✔</span>
                        @else
                            <span class="missing">ABS</span>
                        @endif
                    </td>

                    <td class="col-time">
                        <i>Formation</i>
                    </td>
                    <td class="col-sign">
                        @if($afternoon && $afternoon->is_present)
                            <span class="signed">✔</span>
                        @else
                            <span class="missing">ABS</span>
                        @endif
                    </td>

                    <td class="col-total">{{ $totalDay > 0 ? $totalDay : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- <div class="footer">
        Généré automatiquement le {{ date('d/m/Y à H:i') }}
    </div> -->

</body>
</html>