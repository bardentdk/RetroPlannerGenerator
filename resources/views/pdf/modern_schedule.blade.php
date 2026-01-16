<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Planning de Formation</title>
    <style>
        @page { margin: 0cm; }
        body {
            font-family: 'Helvetica', sans-serif;
            background-color: #f8fafc;
            margin: 1cm;
            color: #1e293b;
        }

        /* HEADER */
        .header {
            margin-bottom: 25px;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            border-left: 6px solid #4f46e5; /* Indigo */
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .title { font-size: 20px; font-weight: 900; color: #1e293b; text-transform: uppercase; }
        .subtitle { font-size: 12px; color: #64748b; margin-top: 4px; }
        .student-name { font-size: 14px; font-weight: bold; color: #4f46e5; margin-top: 8px; }

        /* DAY CARD CONTAINER */
        .day-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 15px;
            page-break-inside: avoid; /* Empêche de couper la carte */
            overflow: hidden;
        }

        /* STRUCTURE TABLEAU */
        .layout-table { width: 100%; border-collapse: collapse; }
        
        /* COLONNE DATE (GAUCHE) */
        .col-date {
            width: 80px;
            background-color: #f1f5f9;
            text-align: center;
            vertical-align: middle;
            border-right: 1px solid #e2e8f0;
        }
        .day-num { font-size: 26px; font-weight: 800; color: #334155; line-height: 1; display: block; }
        .day-name { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-top: 4px; display: block;}

        /* COLONNE CONTENU (CENTRE) */
        .col-content { padding: 0; vertical-align: top; }

        /* LIGNE MATIN / APREM */
        .session-row {
            padding: 10px 15px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .session-row:last-child { border-bottom: none; }

        .time-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 2px;
            display: inline-block;
            width: 60px;
        }
        
        .module-text { font-size: 12px; font-weight: bold; color: #0f172a; display: inline-block;}
        .instructor-text { font-size: 10px; color: #64748b; font-style: italic; display: block; margin-top: 2px; margin-left: 64px; }

        /* COLONNE STATUT (DROITE) */
        .col-status {
            width: 100px;
            vertical-align: middle;
            text-align: center;
            border-left: 1px solid #e2e8f0;
            background-color: #fafafa;
        }

        /* BADGES */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 2px 0;
            width: 70px; /* Taille fixe pour alignement */
            text-align: center;
        }
        .badge-present { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-absent { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-none { background-color: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; }

        .daily-total {
            margin-top: 8px;
            font-size: 10px;
            font-weight: bold;
            color: #6366f1;
        }

        .footer { text-align: center; font-size: 9px; color: #cbd5e1; margin-top: 20px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="title">Planning de Formation</div>
        <!-- <div class="subtitle">{{ $trainingName }}</div> -->
        <div class="subtitle">Mon Passeport pour l'Insertion</div>
        <div class="student-name">Apprenant : {{ $studentName }}</div>
    </div>

    @foreach($days as $date => $slots)
        @php
            $carbonDate = \Carbon\Carbon::parse($date);
            // On récupère les objets spécifiques Matin et Aprèm
            $morning = $slots->firstWhere('period', 'morning');
            $afternoon = $slots->firstWhere('period', 'afternoon');

            // Calcul des heures journalières
            $dailyHours = 0;
            if($morning && $morning->is_present) $dailyHours += 3.5;
            if($afternoon && $afternoon->is_present) $dailyHours += 3.5;
        @endphp

        <div class="day-card">
            <table class="layout-table">
                <tr>
                    <td class="col-date">
                        <span class="day-num">{{ $carbonDate->format('d') }}</span>
                        <span class="day-name">{{ $carbonDate->locale('fr')->isoFormat('MMM') }}</span>
                    </td>

                    <td class="col-content">
                        
                        <div class="session-row">
                            <span class="time-label">Matin</span>
                            @if($morning)
                                <span class="module-text">{{ $morning->module_name ?? 'Module générique' }}</span>
                                <span class="instructor-text">Intervenant : {{ $morning->instructor_name ?? 'Non précisé' }}</span>
                            @else
                                <span class="module-text" style="color:#cbd5e1;">- Pas de session -</span>
                            @endif
                        </div>

                        <div class="session-row">
                            <span class="time-label">Apr-Midi</span>
                            @if($afternoon)
                                <span class="module-text">{{ $afternoon->module_name ?? 'Module générique' }}</span>
                                <span class="instructor-text">Intervenant : {{ $afternoon->instructor_name ?? 'Non précisé' }}</span>
                            @else
                                <span class="module-text" style="color:#cbd5e1;">- Pas de session -</span>
                            @endif
                        </div>

                    </td>

                    <td class="col-status">
                        @if($morning)
                            @if($morning->is_present)
                                <div class="badge badge-present">Mat : Présent</div>
                            @else
                                <div class="badge badge-absent">Mat : Absent</div>
                            @endif
                        @else
                             <div class="badge badge-none">Mat : N/A</div>
                        @endif

                        @if($afternoon)
                            @if($afternoon->is_present)
                                <div class="badge badge-present">PM : Présent</div>
                            @else
                                <div class="badge badge-absent">PM : Absent</div>
                            @endif
                        @else
                             <div class="badge badge-none">PM : N/A</div>
                        @endif

                        <div class="daily-total">
                            Total : {{ $dailyHours }}h
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
<!-- 
    <div class="footer">
        Document généré le {{ date('d/m/Y') }}
    </div> -->

</body>
</html>