<div>
    <livewire:realisasi-program-kerja-documents
        :record="$record"
        :relationship="$relationship"
        :empty-label="$emptyLabel"
        :dapat-pratinjau="$dapatPratinjau ?? false"
        :key="'realisasi-documents-'.$relationship.'-'.$record->getKey()"
    />
</div>
