import React, { useState, useRef } from 'react';
import {
  FolderArchive,
  Upload,
  Download,
  Trash2,
  FileText,
  Image as ImageIcon,
  FileSpreadsheet,
  FileCode,
  FileArchive,
  Eye,
  X,
  Search,
  Filter,
  Loader2,
  HardDrive,
  ShieldCheck,
  CheckCircle2,
} from 'lucide-react';
import { useMediaFiles, useUploadMediaFile, useDeleteMediaFile } from '../../hooks/useSharedServices';
import { MediaFileItem } from '../../api/types';
import { useCRMStore } from '../../store/crmStore';

interface MediaFileManagerViewProps {
  theme?: 'light' | 'dark';
}

function formatBytes(bytes: number, decimals = 2) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function getFileIcon(mime: string) {
  if (mime.startsWith('image/')) return ImageIcon;
  if (mime.includes('spreadsheet') || mime.includes('excel') || mime.includes('csv')) return FileSpreadsheet;
  if (mime.includes('pdf') || mime.includes('word') || mime.includes('text')) return FileText;
  if (mime.includes('zip') || mime.includes('tar') || mime.includes('rar')) return FileArchive;
  return FileCode;
}

export default function MediaFileManagerView({ theme = 'dark' }: MediaFileManagerViewProps) {
  const [mimeFilter, setMimeFilter] = useState<string>('');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedFileForPreview, setSelectedFileForPreview] = useState<MediaFileItem | null>(null);
  const [entityFilter, setEntityFilter] = useState('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  const addToast = useCRMStore((s) => s.addToast);

  const { data: response, isLoading, refetch } = useMediaFiles({
    mime_type: mimeFilter || undefined,
  });

  const uploadMutation = useUploadMediaFile();
  const deleteMutation = useDeleteMediaFile();

  const files = response?.data || [];

  const filteredFiles = files.filter((f) => {
    if (!searchTerm) return true;
    const term = searchTerm.toLowerCase();
    return (
      f.original_name.toLowerCase().includes(term) ||
      f.file_name.toLowerCase().includes(term) ||
      f.mime_type.toLowerCase().includes(term) ||
      (f.user?.name && f.user.name.toLowerCase().includes(term))
    );
  });

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const fileList = e.target.files;
    if (!fileList || fileList.length === 0) return;

    const file = fileList[0];
    if (file.size > 25 * 1024 * 1024) {
      addToast('File exceeds maximum limit of 25MB.', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('file', file);
    if (entityFilter) {
      formData.append('fileable_type', entityFilter);
    }

    uploadMutation.mutate(formData, {
      onSuccess: () => {
        if (fileInputRef.current) fileInputRef.current.value = '';
      },
    });
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100 flex items-center gap-2.5">
            <FolderArchive className="h-6 w-6 text-emerald-500" />
            Tenant Media & Private File Storage
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Secure, encrypted tenant-isolated file repository with temporary signed download URLs and 25MB validation.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <input
            type="file"
            ref={fileInputRef}
            onChange={handleFileUpload}
            className="hidden"
          />
          <button
            onClick={() => fileInputRef.current?.click()}
            disabled={uploadMutation.isPending}
            className="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold flex items-center gap-2 shadow-xs transition-colors"
          >
            {uploadMutation.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Upload className="h-4 w-4" />
            )}
            <span>Upload Attachment</span>
          </button>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div
        className={`p-3 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}
      >
        <div className="relative w-full sm:w-72">
          <Search className="h-4 w-4 absolute left-3 top-2.5 text-slate-400" />
          <input
            type="text"
            placeholder="Search files by name..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-3 py-1.5 rounded-xl border border-slate-700 bg-transparent text-xs focus:outline-none focus:border-emerald-500"
          />
        </div>

        <div className="flex items-center gap-2 w-full sm:w-auto overflow-x-auto">
          <select
            value={mimeFilter}
            onChange={(e) => setMimeFilter(e.target.value)}
            className="px-2.5 py-1.5 rounded-xl border border-slate-700 bg-transparent text-xs focus:outline-none text-slate-300"
          >
            <option value="" className="bg-slate-900">All File Types</option>
            <option value="image/" className="bg-slate-900">Images (PNG, JPG, WEBP)</option>
            <option value="application/pdf" className="bg-slate-900">PDF Documents</option>
            <option value="text/" className="bg-slate-900">Text & Code</option>
            <option value="application/zip" className="bg-slate-900">Archives (ZIP)</option>
          </select>
        </div>
      </div>

      {/* Files Grid / Table */}
      {isLoading ? (
        <div className="py-24 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
          <Loader2 className="h-8 w-8 text-emerald-500 animate-spin" />
          <span>Loading secure storage files...</span>
        </div>
      ) : filteredFiles.length === 0 ? (
        <div
          className={`py-20 rounded-2xl border text-center flex flex-col items-center justify-center p-6 ${
            theme === 'dark' ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'
          }`}
        >
          <HardDrive className="h-10 w-10 text-slate-600 mb-3" />
          <h3 className="font-semibold text-sm text-slate-200">No media files found</h3>
          <p className="text-xs text-slate-400 mt-1 max-w-sm">
            Upload contract PDFs, quotations, receipt images, or project documents to store them in your workspace.
          </p>
          <button
            onClick={() => fileInputRef.current?.click()}
            className="mt-4 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium flex items-center gap-2 transition-colors"
          >
            <Upload className="h-4 w-4 text-emerald-400" />
            <span>Select File to Upload</span>
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {filteredFiles.map((file) => {
            const Icon = getFileIcon(file.mime_type);
            const isImage = file.mime_type.startsWith('image/');

            return (
              <div
                key={file.uuid}
                className={`p-4 rounded-2xl border transition-all hover:border-slate-600 flex flex-col justify-between ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                }`}
              >
                <div>
                  <div className="flex items-start justify-between gap-2 mb-3">
                    <div className="h-10 w-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20 shrink-0">
                      <Icon className="h-5 w-5" />
                    </div>
                    <span className="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700 shrink-0">
                      {formatBytes(file.file_size)}
                    </span>
                  </div>

                  <h4
                    className="font-semibold text-xs text-slate-200 truncate"
                    title={file.original_name}
                  >
                    {file.original_name}
                  </h4>
                  <p className="text-[11px] text-slate-400 font-mono mt-0.5 truncate">
                    {file.mime_type}
                  </p>
                </div>

                <div className="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-1.5 text-slate-400 text-[11px]">
                    <ShieldCheck className="h-3.5 w-3.5 text-emerald-400" />
                    <span>Private (Signed)</span>
                  </div>

                  <div className="flex items-center gap-1">
                    {isImage && file.download_url && (
                      <button
                        onClick={() => setSelectedFileForPreview(file)}
                        className="p-1.5 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200 transition-colors"
                        title="Preview"
                      >
                        <Eye className="h-3.5 w-3.5" />
                      </button>
                    )}
                    {file.download_url && (
                      <a
                        href={file.download_url}
                        target="_blank"
                        rel="noreferrer"
                        className="p-1.5 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-emerald-400 transition-colors"
                        title="Download Secure File"
                      >
                        <Download className="h-3.5 w-3.5" />
                      </a>
                    )}
                    <button
                      onClick={() => {
                        if (confirm(`Delete file "${file.original_name}"?`)) {
                          deleteMutation.mutate(file.uuid);
                        }
                      }}
                      className="p-1.5 rounded-lg hover:bg-rose-950/40 text-slate-400 hover:text-rose-400 transition-colors"
                      title="Delete File"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Image Preview Modal */}
      {selectedFileForPreview && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="relative max-w-3xl w-full bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl">
            <div className="p-3 border-b border-slate-800 flex items-center justify-between">
              <h3 className="font-semibold text-xs text-slate-200 truncate">
                {selectedFileForPreview.original_name}
              </h3>
              <button
                onClick={() => setSelectedFileForPreview(null)}
                className="p-1 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
            <div className="p-4 flex items-center justify-center max-h-[75vh] overflow-auto">
              <img
                src={selectedFileForPreview.download_url}
                alt={selectedFileForPreview.original_name}
                className="max-h-[70vh] object-contain rounded-lg"
              />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
