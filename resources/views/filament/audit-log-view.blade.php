<div class="space-y-4 text-sm">
    <div>
        <h3 class="font-semibold text-gray-800">Action</h3>
        <p class="text-gray-600">{{ $record->action }}</p>
    </div>
    <div>
        <h3 class="font-semibold text-gray-800">Object</h3>
        <p class="text-gray-600">{{ class_basename($record->auditable_type) }} #{{ $record->auditable_id }}</p>
    </div>
    <div>
        <h3 class="font-semibold text-gray-800">Actor</h3>
        <p class="text-gray-600">{{ optional($record->user)->name ?? 'System' }} (ID: {{ optional($record->user)->id ?? 'n/a' }})</p>
    </div>
    <div>
        <h3 class="font-semibold text-gray-800">Changes</h3>
        <pre class="bg-gray-100 p-3 rounded text-xs overflow-auto">{{ json_encode($record->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
    <div>
        <h3 class="font-semibold text-gray-800">Logged At</h3>
        <p class="text-gray-600">{{ optional($record->created_at)->toDayDateTimeString() }}</p>
    </div>
</div>
