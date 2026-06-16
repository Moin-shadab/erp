<style>
.email-chips-container {
    min-height: 38px;
    padding: 6px 12px;
    cursor: text;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background-color: #fff;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    width: 100%;
}
.email-chips-container:focus-within {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
.email-chip {
    background-color: #f8fafc;
    color: #334155;
    border: 1px solid #cbd5e1;
    border-radius: 16px;
    padding: 2px 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 500;
}
.email-chip .btn-close {
    cursor: pointer;
    padding: 0;
    margin: 0;
    width: auto;
    height: auto;
    background: none;
    border: none;
}
.email-chips-input {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
    min-width: 150px;
    font-size: 0.85rem;
    color: #212529;
}
.autocomplete-dropdown {
    position: absolute;
    z-index: 1050;
    background-color: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    max-height: 200px;
    overflow-y: auto;
    width: 100%;
}
.autocomplete-item {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 0.8rem;
    color: #334155;
}
.autocomplete-item:hover {
    background-color: #f1f5f9;
    color: #0f172a;
}
</style>
<div class="container-fluid p-0">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2 text-primary"></i>New Message</h4>
            <p class="text-muted small mb-0">Draft and send emails through SMTP secure sockets.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="d-inline-flex gap-2">
                <button class="btn btn-light border btn-sm" onclick="saveAsDraft()"><i class="bi bi-file-earmark"></i> Save Draft</button>
                <button class="btn btn-primary btn-sm px-3" onclick="sendEmail()"><i class="bi bi-send"></i> Send Message</button>
            </div>
        </div>
    </div>

    <!-- Message Composer Panel -->
    <div class="row">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form id="email-compose-form">
                        <input type="hidden" name="draft_id" id="draft-id">
                        <input type="hidden" name="thread_id" id="thread-id" value="{{ $replyMail ? $replyMail->thread_id : '' }}">

                        <!-- To -->
                        <div class="row mb-3 align-items-center">
                            <label for="c-to" class="col-sm-2 col-form-label small fw-bold text-muted">To:</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control form-control-sm" id="c-to" name="to" required placeholder="recipient@domain.com (comma separated)" value="{{ $replyMail ? $replyMail->from_address : '' }}">
                            </div>
                        </div>

                        <!-- CC / BCC Toggle -->
                        <div class="row mb-3 align-items-center">
                            <label for="c-cc" class="col-sm-2 col-form-label small fw-bold text-muted">CC / BCC:</label>
                            <div class="col-sm-5">
                                <input type="text" class="form-control form-control-sm" id="c-cc" name="cc" placeholder="cc@domain.com">
                            </div>
                            <div class="col-sm-5">
                                <input type="text" class="form-control form-control-sm" id="c-bcc" name="bcc" placeholder="bcc@domain.com">
                            </div>
                        </div>

                        <!-- Subject -->
                        <div class="row mb-3 align-items-center">
                            <label for="c-subject" class="col-sm-2 col-form-label small fw-bold text-muted">Subject:</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control form-control-sm" id="c-subject" name="subject" required placeholder="Enter subject line" value="{{ $replyMail ? (str_starts_with($replyMail->subject, 'Re:') ? $replyMail->subject : 'Re: ' . $replyMail->subject) : '' }}">
                            </div>
                        </div>

                        <!-- Rich Text Editor Toolbar -->
                        <div class="border rounded-t-3 bg-light p-2 d-flex gap-1 align-items-center" style="border-bottom: 0;">
                            <button type="button" class="btn btn-xs btn-white border py-1 px-2 rounded" onclick="execCmd('bold')" title="Bold"><i class="bi bi-type-bold"></i></button>
                            <button type="button" class="btn btn-xs btn-white border py-1 px-2 rounded" onclick="execCmd('italic')" title="Italic"><i class="bi bi-type-italic"></i></button>
                            <button type="button" class="btn btn-xs btn-white border py-1 px-2 rounded" onclick="execCmd('underline')" title="Underline"><i class="bi bi-type-underline"></i></button>
                            <button type="button" class="btn btn-xs btn-white border py-1 px-2 rounded" onclick="execCmd('insertUnorderedList')" title="Bullet List"><i class="bi bi-list-task"></i></button>
                            <button type="button" class="btn btn-xs btn-white border py-1 px-2 rounded" onclick="execCmd('insertOrderedList')" title="Number List"><i class="bi bi-list-ol"></i></button>
                            <div class="vr mx-2"></div>
                            <button type="button" class="btn btn-xs btn-white border py-1 px-2 rounded" onclick="insertLink()" title="Link"><i class="bi bi-link-45deg"></i></button>
                        </div>

                        <!-- Rich Text Editor Area -->
                        <div contenteditable="true" id="compose-body" class="form-control border rounded-b-3 p-3 mb-3" style="min-height: 280px; font-size: 0.85rem; outline:none;">
                            @if($replyMail)
                                <br><br>
                                <hr>
                                <small class="text-muted">On {{ date('M d, Y', strtotime($replyMail->date_sent)) }}, {{ $replyMail->from_name }} wrote:</small>
                                <blockquote class="border-start ps-3 text-muted" style="border-color: #cbd5e1 !important;">
                                    {!! $replyMail->body_html ?: nl2br($replyMail->body_text) !!}
                                </blockquote>
                            @endif
                        </div>

                        <!-- Drag and Drop Attachment zone -->
                        <div class="border border-dashed rounded-3 p-3 text-center mb-3 bg-light" id="dropzone" style="cursor: pointer;">
                            <i class="bi bi-cloud-upload fs-3 text-muted"></i>
                            <p class="small text-muted mb-1">Drag and drop file attachments here, or click to browse</p>
                            <input type="file" id="attachment-input" multiple class="d-none" onchange="handleFileSelect(event)">
                            <div id="file-list" class="d-flex flex-wrap gap-2 justify-content-center mt-2">
                                <!-- Uploaded files badge listing -->
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar: Signature / Templates -->
        <div class="col-lg-3">
            <!-- Templates Card -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0">Email Templates</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="font-size:0.8rem;">
                        @forelse($templates as $tmpl)
                        <button class="list-group-item list-group-item-action py-2 border-0" onclick="applyTemplate({{ json_encode($tmpl) }})">
                            <i class="bi bi-file-earmark-text me-2 text-primary"></i> {{ $tmpl->name }}
                        </button>
                        @empty
                        <div class="p-3 text-center text-muted">No templates saved.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Signature Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0">Email Signature</h6>
                </div>
                <div class="card-body p-3">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="signature-switch" onchange="toggleSignature()" checked>
                        <label class="form-check-input-label small" for="signature-switch">Append signature</label>
                    </div>
                    <div class="border rounded p-2 bg-light font-monospace small" id="sig-content" style="max-height: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        {{ $signature ? strip_tags($signature->content) : 'No default signature' }}
                    </div>
                    <input type="hidden" id="raw-signature" value="{{ $signature ? $signature->content : '' }}">
                </div>
            </div>
        </div>
    </div>
</div>
