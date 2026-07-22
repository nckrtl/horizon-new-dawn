import { Fragment } from "react";

import { CodeBlock } from "@/components/code-block";

const jsonToken =
  /"(?:\\u[a-fA-F0-9]{4}|\\[^u]|[^\\"])*"|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null|[{}[\],:]/g;

function highlightedJson(contents: string) {
  const nodes: React.ReactNode[] = [];
  let cursor = 0;

  for (const match of contents.matchAll(jsonToken)) {
    const index = match.index;

    if (index > cursor) {
      nodes.push(contents.slice(cursor, index));
    }

    const token = match[0];
    const remainder = contents.slice(index + token.length);
    const color = token.startsWith('"')
      ? remainder.trimStart().startsWith(":")
        ? "var(--code-key)"
        : "var(--code-string)"
      : /^[{}[\],:]$/.test(token)
        ? "var(--code-punctuation)"
        : "var(--code-number)";

    nodes.push(
      <span style={{ color }} key={`${index}-${token}`}>
        {token}
      </span>,
    );
    cursor = index + token.length;
  }

  if (cursor < contents.length) {
    nodes.push(contents.slice(cursor));
  }

  return nodes.map((node, index) => <Fragment key={index}>{node}</Fragment>);
}

export function JsonPayload({ value }: { value: unknown }) {
  if (value === null || value === undefined) {
    return <p className="px-6 py-4 text-sm text-muted-foreground">Payload unavailable</p>;
  }

  const contents = typeof value === "string" ? value : JSON.stringify(value, null, 4);

  return (
    <CodeBlock className="whitespace-pre-wrap break-words">
      <code>{highlightedJson(contents)}</code>
    </CodeBlock>
  );
}
