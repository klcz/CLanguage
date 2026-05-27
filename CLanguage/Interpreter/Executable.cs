using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using CLanguage.Syntax;
using CLanguage.Types;
using System.Text;
using System.Runtime.Intrinsics.X86;
using System.Xml.Linq;
using Microsoft.VisualBasic;
using System.Diagnostics.Metrics;
using System.Net;

namespace CLanguage.Interpreter
{
    public class Executable
    {
        public MachineInfo MachineInfo { get; private set; }

        public List<BaseFunction> Functions { get; private set; }

        readonly List<CompiledVariable> globals = new List<CompiledVariable>();
        public IReadOnlyList<CompiledVariable> Globals => globals;

        readonly List<TypeHierarchyEntry> typeHierarchy = new List<TypeHierarchyEntry>();

        /// <summary>
        /// Compile-time type hierarchy table for all polymorphic types.
        /// Each entry maps a type ID to its base type ID and name,
        /// enabling future RTTI features like <c>dynamic_cast</c> and <c>typeid</c>.
        /// </summary>
        public IReadOnlyList<TypeHierarchyEntry> TypeHierarchy => typeHierarchy;

        public Executable(MachineInfo machineInfo)
        {
            MachineInfo = machineInfo;
            Functions = new List<BaseFunction>();
            Functions.AddRange(machineInfo.InternalFunctions.Cast<BaseFunction>());
        }

        public CompiledVariable AddGlobal(string name, CType type)
        {
            var last = Globals.LastOrDefault();
            var offset = last == null ? 0 : last.StackOffset + last.VariableType.NumValues;
            var v = new CompiledVariable(name, offset, type);
            globals.Add(v);
            return v;
        }

        public void AddTypeHierarchyEntry(TypeHierarchyEntry entry)
        {
            typeHierarchy.Add(entry);
        }

        public Value GetConstantMemory(string stringConstant)
        {
            var index = Globals.Count;
            var bytes = Encoding.UTF8.GetBytes(stringConstant);
            var len = bytes.Length + 1;
            var type = new CArrayType(CBasicType.SignedChar, len);
            var v = AddGlobal("__c" + Globals.Count, type);
            v.InitialValue = bytes.Concat(new byte[] { 0 }).Select(x => (Value)x).ToArray();
            return Value.Pointer(v.StackOffset);
        }

        public static bool MapF<V>(IEnumerable<BaseFunction> m, Action<V> action) where V : BaseFunction
        {
            foreach (var obj in m)
            {
                if (obj is V v)
                {
                    action(v);
                }
            }
            return true;
        }

        public static string IfAppend(string s, string append)
        {
            if(s.Length == 0)
            {
                return "";
            }
            return s + append;
        }

        public string DumpOp(bool internalFunction = false)
        {
            var s = "";
            var m = MachineInfo;
            var g = Globals;
            var t = TypeHierarchy;
            var f = Functions;
            // fmt.Sprintf("MachineInfo: { IntSize=%d, PointerSize=%d, LongIntSize=%d, DoubleSize=%d }\n", m.IntSize, m.PointerSize, m.LongIntSize, m.DoubleSize)
            s += $"MachineInfo: {{IntSize ={m.IntSize}, PointerSize={m.PointerSize}, LongIntSize={m.LongIntSize}, DoubleSize={m.DoubleSize} }}\n";


            if (g.Count > 0)
            {
                s += "Globals:\n";
                foreach (var global in g)
                {
                    s += $"\t{global.Name} <{global.VariableType}> = {global.InitialValue}\n";
                }
            }

            if (t.Count > 0)
            {
                s += "Types:\n";
                foreach (var typ in t)
                {
                    s += $"\t{typ.TypeName} <{typ.TypeId}> @ {typ.BaseTypeId}\n";
                }
            }

            if (f.Count > 0)
            {
                s += "Functions:\n";

                _ = internalFunction && MapF(f, (InternalFunction ifun) =>
                {
                    var nc = IfAppend(ifun.NameContext, "::");
                    s += $"\t{nc + ifun.Name} `{ifun.Action}` {ifun.FunctionType}\n";

                });
                MapF(f, (CompiledFunction cfun) =>
                {
                    var nc = IfAppend(cfun.NameContext, "::");
                    s += $"\t{nc + cfun.Name} {cfun.FunctionType}\n";
                    var oi = cfun.Instructions;
                    foreach (var ins in oi) {
                        var op = ins.Op.ToString().Replace("OpCode(", "Op(");
                        op = op.Replace("OpCode", "");
                        s += $"\t\t{op} {ins.X}\n";
                    }
                });
            }

            return s;
        }
    }
}

