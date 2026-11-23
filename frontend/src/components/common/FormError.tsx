import React from 'react';
import { AlertCircle } from 'lucide-react';

interface FormErrorProps {
  errors?: string[] | string | null;
  className?: string;
}

export default function FormError({ errors, className = '' }: FormErrorProps) {
  if (!errors) return null;

  const errorList = Array.isArray(errors) ? errors : [errors];
  if (errorList.length === 0) return null;

  return (
    <div className={`mt-1.5 space-y-1 ${className}`}>
      {errorList.map((err, idx) => (
        <div key={idx} className="flex items-center gap-1.5 text-xs text-rose-400">
          <AlertCircle className="h-3.5 w-3.5 shrink-0" />
          <span>{err}</span>
        </div>
      ))}
    </div>
  );
}
