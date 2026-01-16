<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Planning de Formation</title>
    <style>
        body { font-family: sans-serif; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #e11d48; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #e11d48; }
        .header p { margin: 5px 0; color: #666; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 14px; }
        th { background-color: #f3f4f6; color: #444; font-weight: bold; }
        
        .status-present { color: #059669; font-weight: bold; } /* Vert */
        .status-absent { color: #be123c; font-weight: bold; } /* Rouge */
        
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relevé de Présences</h1>
        <p>Stagiaire : <strong>{{ $studentName }}</strong></p>
        <p>Date d'édition : {{ date('d/m/Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 25%">Date</th>
                <th style="width: 20%">Période</th>
                <th style="width: 30%">Statut</th>
                <th style="width: 25%">Fichier Source</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schedule as $date => $slots)
                @foreach($slots as $slot)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($slot->date)->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</td>
                        <td>{{ $slot->period === 'morning' ? 'Matin' : 'Après-midi' }}</td>
                        <td>
                            @if($slot->is_present)
                                <span class="status-present">PRÉSENT / SIGNÉ</span>
                            @else
                                <span class="status-absent">ABSENT / NON SIGNÉ</span>
                            @endif
                        </td>
                        <td style="font-size: 10px; color: #888;">{{ $slot->source_file }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Document généré automatiquement par PlanningGen Réunion.
    </div>
</body>
</html>