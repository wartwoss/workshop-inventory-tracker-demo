import { useId } from 'react'

export function FormField({
  label,
  error,
  hint,
  required = false,
  children,
  htmlFor,
}) {
  const generatedId = useId()
  const id = htmlFor || generatedId

  return (
    <div className={`form-field${error ? ' has-error' : ''}`}>
      <label htmlFor={id}>
        {label}
        {required ? <span className="required-mark"> *</span> : null}
      </label>
      {children({ id, 'aria-invalid': Boolean(error), 'aria-describedby': error ? `${id}-error` : undefined })}
      {hint && !error ? <p className="field-hint">{hint}</p> : null}
      {error ? (
        <p className="field-error" id={`${id}-error`}>
          {error}
        </p>
      ) : null}
    </div>
  )
}
