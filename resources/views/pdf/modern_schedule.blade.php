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

        /* --- HEADER --- */
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
        
        .header-infos { width: 100%; margin-top: 15px; border-top: 1px solid #f1f5f9; padding-top: 10px; }
        .info-label { font-size: 9px; text-transform: uppercase; color: #94a3b8; font-weight: bold; }
        .info-value { font-size: 14px; font-weight: bold; color: #334155; }
        .total-box { text-align: right; }
        .total-val { font-size: 22px; font-weight: 900; color: #4f46e5; }

        /* --- DAY CARD --- */
        .day-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 12px;
            page-break-inside: avoid;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }

        .layout-table { width: 100%; border-collapse: collapse; }
        
        /* 1. COLONNE DATE (GAUCHE) - Largeur fixe */
        .col-date {
            width: 90px;
            background-color: #f8fafc;
            text-align: center;
            vertical-align: middle;
            border-right: 1px solid #e2e8f0;
        }
        .day-num { font-size: 24px; font-weight: 800; color: #334155; line-height: 1; display: block; }
        .day-name { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-top: 4px; display: block;}

        /* 2. COLONNE CONTENU (CENTRE) - Prend tout le reste */
        .col-content { padding: 0; vertical-align: top; }

        .session-row {
            padding: 12px 20px; /* Plus d'espace interne */
            border-bottom: 1px dashed #e2e8f0;
            height: auto;
        }
        .session-row:last-child { border-bottom: none; }

        .time-badge {
            font-size: 9px; font-weight: bold; text-transform: uppercase;
            padding: 3px 0; border-radius: 4px; margin-right: 12px;
            display: inline-block; width: 60px; text-align: center;
            vertical-align: middle;
        }
        .bg-matin { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
        .bg-aprem { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

        .module-text { font-size: 13px; font-weight: bold; color: #0f172a; vertical-align: middle; }
        .instructor-text { 
            font-size: 10px; color: #64748b; font-style: italic; 
            display: block; margin-top: 4px; margin-left: 76px; /* Aligné sous le texte du module */
        }

        .footer { position: fixed; bottom: 0.5cm; width: 100%; text-align: center; font-size: 9px; color: #cbd5e1; }
    </style>
</head>
<body>

    <div class="header">
        <div class="title">Programme Prévisionnel</div>
        <!-- <div class="subtitle">{{ $trainingName }}</div> -->
        <div class="subtitle">
            Mon Passeport pour l'Insertion
        </div>
        
        <table class="header-infos">
            <tr>
                <td>
                    <div class="info-label">Apprenant concerné</div>
                    <div class="info-value">{{ $studentName }}</div>
                </td>
                <td class="total-box">
                    <div class="total-val">{{ $totalHeures }}h</div>
                    <div class="info-label">Volume Horaire Total</div>
                </td>
            </tr>
        </table>
    </div>

    @foreach($schedule as $date => $periods)
        @php
            $carbonDate = \Carbon\Carbon::parse($date);
        @endphp

        <div class="day-card">
            <table class="layout-table">
                <tr>
                    <td class="col-date">
                        <span class="day-num">{{ $carbonDate->format('d') }}</span>
                        <span class="day-name">{{ $carbonDate->locale('fr')->isoFormat('MMM') }}</span>
                        <div style="font-size:8px; color:#cbd5e1; margin-top:2px;">{{ $carbonDate->format('Y') }}</div>
                    </td>

                    <td class="col-content">
                        
                        <div class="session-row">
                            <span class="time-badge bg-matin">Matin</span>
                            @if($periods['morning'])
                                <span class="module-text">{{ $periods['morning']->module_name ?? 'Module de formation' }}</span>
                                <span class="instructor-text">Intervenant : {{ $periods['morning']->instructor_name ?? 'Non précisé' }}</span>
                            @else
                                <span class="module-text" style="color:#cbd5e1; font-style:italic;">-</span>
                            @endif
                        </div>

                        <div class="session-row">
                            <span class="time-badge bg-aprem">Ap-Midi</span>
                            @if($periods['afternoon'])
                                <span class="module-text">{{ $periods['afternoon']->module_name ?? 'Module de formation' }}</span>
                                <span class="instructor-text">Intervenant : {{ $periods['afternoon']->instructor_name ?? 'Non précisé' }}</span>
                            @else
                                <span class="module-text" style="color:#cbd5e1; font-style:italic;">-</span>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach

    <div class="footer">
        <!-- Document généré le {{ date('d/m/Y') }} -->
    </div>

</body>
</html>